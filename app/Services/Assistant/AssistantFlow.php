<?php

namespace App\Services\Assistant;

use App\Enums\AssistantMessageRole;
use App\Enums\AssistantState;
use App\Models\AssistantConversation;
use App\Models\AssistantSetting;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Visitor flow: Saudação → Necessidade → Serviço → Cliente → Data → Horário → Confirmação.
 *
 * The flow only moves forward with data the backend has checked. Options are read from the
 * availability rules, the booking goes through AssistantOperations, and the success message is
 * written only after the scheduler has confirmed the appointment. The optional language-model
 * provider is consulted only to pick a service when the text match fails, and its answer is
 * accepted only if it names one of the listed services.
 */
class AssistantFlow
{
    /** Failed attempts in a step before the conversation goes to a human. */
    private const int MAX_INVALID_ATTEMPTS = 3;

    /** Options shown per step. */
    private const int MAX_OPTIONS = 8;

    /** Alternatives offered when the chosen slot is no longer free. */
    private const int MAX_ALTERNATIVES = 3;

    /** Characters of a service description shown in the greeting. */
    private const int MAX_DESCRIPTION_CHARS = 80;

    public function __construct(
        private AssistantText $text,
        private AssistantOperations $operations,
        private AssistantConversations $conversations,
        private AssistantBudget $budget,
        private AssistantProvider $provider,
    ) {}

    /**
     * First message of a new conversation: greeting and the list of services.
     */
    public function greet(Tenant $tenant, AssistantSetting $setting, AssistantConversation $conversation): void
    {
        $services = $this->operations->activeServices($tenant);

        if ($services->isEmpty()) {
            $this->transfer($tenant, $conversation, 'Nenhum serviço ativo cadastrado.');
            $this->say($conversation, 'No momento não há serviços disponíveis para agendamento online. Vou transferir você para um atendente.');

            return;
        }

        $intro = 'Olá! Sou a recepcionista virtual de '.($setting->display_name ?: $tenant->name).'.';
        $extra = filled($setting->greeting) ? ' '.$setting->greeting : '';

        $this->say($conversation, $intro.$extra.' Qual serviço você precisa? Nossos serviços: '.$this->serviceCatalog($services).'.');
        $this->move($conversation, AssistantState::Service);
    }

    /**
     * Handle one visitor message. Stores the message, then replies or hands off.
     */
    public function receive(Tenant $tenant, AssistantSetting $setting, AssistantConversation $conversation, string $body): void
    {
        $body = trim(mb_substr($body, 0, 500));

        $conversation->forceFill(['last_activity_at' => now()])->save();
        $this->conversations->appendMessage($conversation, AssistantMessageRole::Visitor, $body);

        if (! $conversation->status->allowsAutomaticReplies()) {
            return;
        }

        if (! $this->budget->messagesAllowed($tenant)) {
            $this->transfer($tenant, $conversation, 'Limite diário de atendimento automático atingido.');
            $this->say($conversation, 'Atingimos o limite de atendimento automático de hoje. Vou transferir você para um atendente.');

            return;
        }

        $this->budget->recordMessage($tenant);

        if ($this->text->asksForHuman($body)) {
            $this->transfer($tenant, $conversation, 'Cliente pediu atendimento humano.');
            $this->say($conversation, 'Certo, vou transferir você para um atendente. Ele responderá por aqui.');

            return;
        }

        if ($this->text->asksForStatus($body) && $conversation->service_request_id !== null) {
            $this->replyStatus($tenant, $conversation);

            return;
        }

        if (in_array($conversation->state, [AssistantState::Greeting, AssistantState::Service, AssistantState::Done], true) && $this->replyInformation($tenant, $setting, $conversation, $body)) {
            return;
        }

        match ($conversation->state) {
            AssistantState::Greeting, AssistantState::Need, AssistantState::Service => $this->handleService($tenant, $conversation, $body),
            AssistantState::Customer => $this->handleCustomer($tenant, $conversation, $body),
            AssistantState::Date => $this->handleDate($tenant, $conversation, $body),
            AssistantState::Slots => $this->handleSlots($tenant, $conversation, $body),
            AssistantState::Confirm => $this->handleConfirm($tenant, $conversation, $body),
            AssistantState::Done => $this->say($conversation, 'Seu agendamento já está registrado. Posso ajudar com mais alguma coisa? Diga "status" para consultar ou "atendente" para falar com alguém.'),
            AssistantState::Human => null,
        };
    }

    private function handleService(Tenant $tenant, AssistantConversation $conversation, string $body): void
    {
        $services = $this->operations->activeServices($tenant);

        if ($services->isEmpty()) {
            $this->transfer($tenant, $conversation, 'Nenhum serviço ativo cadastrado.');
            $this->say($conversation, 'No momento não há serviços disponíveis para agendamento online. Vou transferir você para um atendente.');

            return;
        }

        $service = $this->text->matchService($body, $services) ?? $this->classifyWithProvider($tenant, $conversation, $body, $services);

        if ($service === null) {
            if ($this->registerInvalid($tenant, $conversation)) {
                $this->say($conversation, 'Não consegui identificar o serviço. Vou transferir você para um atendente.');

                return;
            }

            $this->say($conversation, 'Não encontrei esse serviço. Nossos serviços: '.$this->serviceList($services).'. Diga o nome do serviço que você precisa.');

            return;
        }

        $conversation->forceFill(['service_id' => $service->getKey()])->save();

        if ($conversation->customer_id !== null) {
            $this->move($conversation, AssistantState::Date);
            $this->say($conversation, 'Certo: '.$service->name.'. Para qual dia você quer agendar? Responda hoje, amanhã ou uma data como 25/11.');

            return;
        }

        $this->move($conversation, AssistantState::Customer);
        $this->say($conversation, 'Certo: '.$service->name.'. Aviso de privacidade: usaremos seu nome e telefone somente para este atendimento e para o agendamento. Para continuar, informe seu nome e o telefone com DDD.');
    }

    private function handleCustomer(Tenant $tenant, AssistantConversation $conversation, string $body): void
    {
        $collected = $conversation->collected ?? [];

        $phone = $this->text->phone($body);
        $name = $collected['name'] ?? $this->text->name($body);

        if ($phone !== null) {
            $collected['phone'] = $phone;
        }

        if (($collected['name'] ?? null) === null && $name !== null) {
            $collected['name'] = $name;
        }

        $name = $collected['name'] ?? null;
        $phone = $collected['phone'] ?? null;

        if ($name !== null && $phone !== null) {
            $customer = $this->operations->findOrCreateCustomer($tenant, $name, $phone);

            $conversation->forceFill(['customer_id' => $customer->getKey(), 'collected' => []])->save();
            $this->move($conversation, AssistantState::Date);

            $this->say($conversation, 'Obrigado, '.explode(' ', $customer->name)[0].'. Para qual dia você quer agendar? Responda hoje, amanhã ou uma data como 25/11.');

            return;
        }

        $conversation->forceFill(['collected' => $collected])->save();

        if ($this->registerInvalid($tenant, $conversation, $phone === null && $name === null && trim($body) !== '')) {
            $this->say($conversation, 'Não consegui registrar seus dados. Vou transferir você para um atendente.');

            return;
        }

        $this->say($conversation, match (true) {
            $name === null && $phone === null => 'Preciso do seu nome e do telefone com DDD para continuar.',
            $name === null => 'Qual é o seu nome?',
            default => 'Qual é o seu telefone com DDD?',
        });
    }

    private function handleDate(Tenant $tenant, AssistantConversation $conversation, string $body): void
    {
        $day = $this->text->date($body, $tenant);

        if ($day === null) {
            if ($this->registerInvalid($tenant, $conversation)) {
                $this->transfer($tenant, $conversation, 'Data não identificada após 3 tentativas.');
                $this->say($conversation, 'Não consegui identificar a data. Vou transferir você para um atendente.');

                return;
            }

            $this->say($conversation, 'Escolha uma data entre hoje e 60 dias à frente. Responda "hoje", "amanhã" ou um dia e mês, como 25/11.');

            return;
        }

        $preferredTime = $this->text->time($body);

        if ($preferredTime !== null) {
            $conversation->forceFill(['collected' => [...($conversation->collected ?? []), 'preferred_time' => $preferredTime]])->save();
        }

        $service = Service::query()->forTenant($tenant)->whereKey($conversation->service_id)->firstOrFail();
        $options = $this->operations->availableSlots($tenant, $service, $day);

        if ($options === []) {
            $this->say($conversation, 'Não há horários livres em '.$day->format('d/m/Y').' para '.$service->name.'. Escolha outra data, ou diga "atendente" para falar com a equipe.');

            return;
        }

        $this->offerSlots($conversation, $day, $this->nearestFirst($options, $conversation->collected['preferred_time'] ?? null));
    }

    private function handleSlots(Tenant $tenant, AssistantConversation $conversation, string $body): void
    {
        if ($this->text->asksForAnotherDate($body)) {
            $conversation->forceFill(['collected' => $this->without($conversation, ['date', 'options', 'choice'])])->save();
            $this->move($conversation, AssistantState::Date);
            $this->say($conversation, 'Qual outra data você prefere?');

            return;
        }

        $shown = array_values(array_slice($conversation->collected['options'] ?? [], 0, self::MAX_OPTIONS));
        $number = $this->text->optionNumber($body);

        if ($number === null || $number < 1 || $number > count($shown)) {
            if ($this->registerInvalid($tenant, $conversation)) {
                $this->transfer($tenant, $conversation, 'Horário não escolhido após 3 tentativas.');
                $this->say($conversation, 'Não consegui identificar o horário escolhido. Vou transferir você para um atendente.');

                return;
            }

            $this->say($conversation, 'Responda com o número de um dos horários listados.');

            return;
        }

        $choice = $shown[$number - 1];
        $conversation->forceFill(['collected' => [...($conversation->collected ?? []), 'choice' => $choice]])->save();
        $this->move($conversation, AssistantState::Confirm);

        $service = Service::query()->forTenant($tenant)->whereKey($conversation->service_id)->firstOrFail();
        $day = CarbonImmutable::createFromFormat('Y-m-d', $conversation->collected['date'], $tenant->timezone);

        $this->say($conversation, 'Confirma '.$service->name.' com '.$choice['professional'].' em '.$day->format('d/m/Y').' às '.$choice['time'].'? Responda sim para confirmar ou não para escolher outro horário.');
    }

    private function handleConfirm(Tenant $tenant, AssistantConversation $conversation, string $body): void
    {
        if ($this->text->isNo($body)) {
            $conversation->forceFill(['collected' => $this->without($conversation, ['date', 'options', 'choice'])])->save();
            $this->move($conversation, AssistantState::Date);
            $this->say($conversation, 'Sem problema. Qual outra data você prefere?');

            return;
        }

        if (! $this->text->isYes($body)) {
            if ($this->registerInvalid($tenant, $conversation)) {
                $this->transfer($tenant, $conversation, 'Confirmação não obtida após 3 tentativas.');
                $this->say($conversation, 'Não consegui confirmar o agendamento. Vou transferir você para um atendente.');

                return;
            }

            $this->say($conversation, 'Responda sim para confirmar ou não para escolher outro horário.');

            return;
        }

        $this->book($tenant, $conversation);
    }

    /**
     * Book the chosen slot. The request and the appointment are written in one transaction, and the visitor is told
     * of the appointment only after it is saved. A slot that is no longer free is not booked and not replaced: the
     * transaction is rolled back, the availability is checked again, and up to three real alternatives are offered
     * for a new choice and a new confirmation.
     */
    private function book(Tenant $tenant, AssistantConversation $conversation): void
    {
        $choice = $conversation->collected['choice'] ?? null;

        if ($choice === null) {
            $this->move($conversation, AssistantState::Slots);

            return;
        }

        $service = Service::query()->forTenant($tenant)->whereKey($conversation->service_id)->firstOrFail();
        $professional = Professional::query()->forTenant($tenant)->whereKey($choice['professional_id'])->first();
        $startsAt = CarbonImmutable::parse($choice['starts_at']);

        try {
            [$request, $appointment] = DB::transaction(function () use ($tenant, $conversation, $professional, $startsAt): array {
                if ($professional === null) {
                    throw ValidationException::withMessages(['professional_id' => ['Este profissional não está mais disponível.']]);
                }

                $request = $this->operations->registerRequest($tenant, $conversation, $this->conversations->summarize($conversation));
                $appointment = $this->operations->requestAppointment(
                    $tenant,
                    $conversation,
                    $request,
                    $professional,
                    $startsAt,
                    $this->operations->bookingKey($conversation, $professional, $startsAt),
                );

                return [$request, $appointment];
            });
        } catch (ValidationException) {
            $conversation->refresh();
            $this->offerAlternatives($tenant, $conversation, $service, (string) $choice['time']);

            return;
        } catch (\Throwable $exception) {
            Log::error('assistant.booking_failed', ['tenant_id' => $tenant->getKey(), 'error' => $exception::class]);
            $this->transfer($tenant, $conversation, 'Falha técnica ao concluir o agendamento.');
            $this->say($conversation, 'Não consegui concluir o agendamento agora. Vou transferir você para um atendente, que continua o atendimento sem você repetir os dados.');

            return;
        }

        $conversation->forceFill(['collected' => $this->without($conversation, ['date', 'options', 'choice', 'summary'])])->save();
        $this->move($conversation, AssistantState::Done);
        $this->operations->registerRequest($tenant, $conversation, $this->conversations->summarize($conversation));

        $local = $tenant->toLocal($appointment->starts_at);
        $this->say($conversation, 'Agendamento confirmado: '.$service->name.' com '.$professional->name.' em '.$local->format('d/m/Y').' às '.$local->format('H:i').'. Número da solicitação: #'.$request->getKey().'.');
    }

    /**
     * The chosen slot was not booked. Availability is read again for the same day and up to three free slots
     * closest to the visitor's preference are offered. Nothing is reserved until the visitor chooses and confirms again.
     */
    private function offerAlternatives(Tenant $tenant, AssistantConversation $conversation, Service $service, string $chosenTime): void
    {
        $day = CarbonImmutable::createFromFormat('Y-m-d', $conversation->collected['date'], $tenant->timezone);
        $target = $conversation->collected['preferred_time'] ?? $chosenTime;

        $conversation->forceFill(['collected' => $this->without($conversation, ['choice', 'options'])])->save();

        $options = $this->nearestFirst($this->operations->availableSlots($tenant, $service, $day), $target);

        if ($options === []) {
            $conversation->forceFill(['collected' => $this->without($conversation, ['date', 'options', 'choice'])])->save();
            $this->move($conversation, AssistantState::Date);
            $this->say($conversation, 'Este horário não foi reservado, e não há mais horários livres em '.$day->format('d/m/Y').' para '.$service->name.'. Escolha outra data, ou diga "atendente" para falar com a equipe.');

            return;
        }

        $this->offerSlots($conversation, $day, $options, self::MAX_ALTERNATIVES, 'Este horário não foi reservado, pois ficou indisponível antes da confirmação. Horários livres em '.$day->format('d/m/Y').' mais próximos do pedido:');
    }

    /**
     * Information the assistant may give only from stored tenant data. Returns false when the text is not an information request.
     */
    private function replyInformation(Tenant $tenant, AssistantSetting $setting, AssistantConversation $conversation, string $body): bool
    {
        if ($this->text->asksForPrice($body)) {
            $service = $this->text->matchService($body, $this->operations->activeServices($tenant))
                ?? ($conversation->service_id !== null ? Service::query()->forTenant($tenant)->whereKey($conversation->service_id)->first() : null);

            if ($service === null) {
                $this->say($conversation, 'De qual serviço você quer saber o valor? Nossos serviços: '.$this->serviceList($this->operations->activeServices($tenant)).'.');

                return true;
            }

            $details = $this->operations->serviceDetails($service);
            $price = $details['price'] === null ? 'sob consulta' : 'R$ '.$details['price'].' ('.$this->priceLabel($service).')';
            $duration = $details['duration_minutes'] === null ? '' : ' Duração: '.$details['duration_minutes'].' min.';

            $this->say($conversation, $service->name.': '.$price.'.'.$duration);

            return true;
        }

        if ($this->text->asksForHours($body)) {
            $this->say($conversation, filled($setting->business_hours_text)
                ? 'Horário de atendimento: '.$setting->business_hours_text.'.'
                : 'Não tenho o horário de atendimento cadastrado. Posso transferir você para um atendente.');

            return true;
        }

        if ($this->text->asksForRegion($body)) {
            $this->say($conversation, filled($setting->service_region)
                ? 'Região de atendimento: '.$setting->service_region.'.'
                : 'Não tenho a região de atendimento cadastrada. Posso transferir você para um atendente.');

            return true;
        }

        return false;
    }

    private function replyStatus(Tenant $tenant, AssistantConversation $conversation): void
    {
        $status = $this->operations->requestStatus($tenant, $conversation);

        if ($status['request_status'] === null) {
            $this->say($conversation, 'Ainda não há solicitação registrada nesta conversa.');

            return;
        }

        $this->say($conversation, 'Situação da solicitação: '.$status['request_status'].'.'.($status['appointment'] !== null
            ? ' Agendamento: '.$status['appointment'].'.'
            : ' Ainda sem horário confirmado.'));
    }

    /**
     * @param  list<array{professional_id: int, professional: string, time: string, starts_at: string}>  $options
     */
    private function offerSlots(AssistantConversation $conversation, CarbonImmutable $day, array $options, int $limit = self::MAX_OPTIONS, ?string $intro = null): void
    {
        $stored = array_slice($options, 0, $limit);
        $shown = $stored;

        $lines = [];

        foreach ($shown as $index => $option) {
            $lines[] = ($index + 1).') '.$option['time'].' — '.$option['professional'];
        }

        $conversation->forceFill([
            'collected' => [...($conversation->collected ?? []), 'date' => $day->format('Y-m-d'), 'options' => $stored],
        ])->save();

        $preferredTime = $conversation->collected['preferred_time'] ?? null;
        $header = $intro ?? 'Horários disponíveis em '.$day->format('d/m/Y').($preferredTime === null ? '' : ', os mais próximos de '.$preferredTime.' (sua preferência, ainda não reservada)').':';
        $footer = $intro === null
            ? 'Responda com o número do horário.'
            : 'Responda com o número do horário e confirme em seguida. Para outro dia, diga "outra data"; para a equipe, diga "atendente".';

        $this->move($conversation, AssistantState::Slots);
        $this->say($conversation, $header."\n".implode("\n", $lines)."\n".$footer);
    }

    /**
     * Options ordered by the distance to the time the visitor asked for, so the closest one is offered first.
     *
     * @param  list<array{professional_id: int, professional: string, time: string, starts_at: string}>  $options
     * @return list<array{professional_id: int, professional: string, time: string, starts_at: string}>
     */
    private function nearestFirst(array $options, ?string $preferredTime): array
    {
        if ($preferredTime === null) {
            return $options;
        }

        $target = $this->minutesOfDay($preferredTime);

        usort($options, fn (array $a, array $b): int => [abs($this->minutesOfDay($a['time']) - $target), $a['time'], $a['professional']]
            <=> [abs($this->minutesOfDay($b['time']) - $target), $b['time'], $b['professional']]);

        return $options;
    }

    private function minutesOfDay(string $time): int
    {
        [$hour, $minute] = array_map(intval(...), explode(':', $time));

        return $hour * 60 + $minute;
    }

    /**
     * Hand the conversation to the team. When the visitor is identified, the request enters the company's triage
     * queue with origin "Site / IA" and the summary, so the team starts from the same record.
     */
    private function transfer(Tenant $tenant, AssistantConversation $conversation, string $reason): void
    {
        if ($conversation->customer_id !== null) {
            $this->operations->registerRequest($tenant, $conversation, $this->conversations->summarize($conversation, $reason));
        }

        $this->conversations->handoff($conversation, $reason);
    }

    /**
     * Ask the provider to pick a service from the text match that failed. Its answer is accepted only as an index of the listed services.
     */
    private function classifyWithProvider(Tenant $tenant, AssistantConversation $conversation, string $body, Collection $services): ?Service
    {
        if ($this->provider->name() === 'none' || ! $this->budget->providerAllowed($tenant)) {
            return null;
        }

        $context = [
            'services' => $services->values()->map(fn (Service $service, int $index): array => ['index' => $index + 1, 'name' => $service->name])->all(),
            'state' => $conversation->state->value,
            'max_tokens' => (int) config('assistant.max_provider_tokens_per_call'),
        ];

        try {
            $answer = $this->provider->classify(mb_substr($body, 0, 500), $context);
        } catch (\Throwable $exception) {
            $this->budget->recordProviderFailure($tenant);
            Log::warning('assistant.provider_failed', ['tenant_id' => $tenant->getKey(), 'provider' => $this->provider->name(), 'error' => $exception::class]);

            return null;
        }

        $this->budget->recordProviderCall($tenant, intdiv(mb_strlen($body), 4) + 40);

        if (($answer['intent'] ?? null) !== 'select_service' || ! is_int($answer['service_index'] ?? null)) {
            return null;
        }

        return $services->values()->get($answer['service_index'] - 1);
    }

    /**
     * Count a failed attempt in the current step. Returns true when the conversation must go to a human.
     */
    private function registerInvalid(Tenant $tenant, AssistantConversation $conversation, bool $countIt = true): bool
    {
        if (! $countIt) {
            return false;
        }

        $conversation->forceFill(['invalid_attempts' => $conversation->invalid_attempts + 1])->save();

        if ($conversation->invalid_attempts >= self::MAX_INVALID_ATTEMPTS) {
            $this->transfer($tenant, $conversation, 'Atendimento automático sem avanço após '.self::MAX_INVALID_ATTEMPTS.' tentativas.');

            return true;
        }

        return false;
    }

    private function move(AssistantConversation $conversation, AssistantState $state): void
    {
        $conversation->forceFill(['state' => $state, 'invalid_attempts' => 0])->save();
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function without(AssistantConversation $conversation, array $keys): array
    {
        return array_diff_key($conversation->collected ?? [], array_flip($keys));
    }

    private function say(AssistantConversation $conversation, string $body): void
    {
        $this->conversations->appendMessage($conversation, AssistantMessageRole::Assistant, $body);
    }

    /**
     * @param  Collection<int, Service>  $services
     */
    private function serviceList(Collection $services): string
    {
        return $services->take(self::MAX_OPTIONS)->pluck('name')->implode(', ');
    }

    /**
     * Names with the short description stored for each service, as the company wrote them.
     *
     * @param  Collection<int, Service>  $services
     */
    private function serviceCatalog(Collection $services): string
    {
        return $services->take(self::MAX_OPTIONS)
            ->map(fn (Service $service): string => filled($service->description)
                ? $service->name.' ('.Str::limit(trim((string) $service->description), self::MAX_DESCRIPTION_CHARS, '…').')'
                : $service->name)
            ->implode('; ');
    }

    private function priceLabel(Service $service): string
    {
        return match ($service->price_type->value) {
            'fixed' => 'valor fixo',
            'starting_at' => 'a partir de',
            default => 'sob consulta',
        };
    }
}
