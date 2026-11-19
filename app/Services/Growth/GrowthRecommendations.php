<?php

namespace App\Services\Growth;

use App\Enums\AppointmentStatus;
use App\Enums\ConversationStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\AssistantConversation;
use App\Models\ServiceRequest;
use App\Models\Tenant;

/**
 * What the company can do now, read from its own records with plain queries (no AI model is called): requests and
 * conversations waiting for the team, visits in the next two days, requests without movement, completed services
 * without the amount, and a fall in conversion against the previous period when both periods have enough requests.
 * Each item carries the count and a link to the records behind it. Nothing is shown when there is nothing to do.
 */
class GrowthRecommendations
{
    /** Hours ahead considered "upcoming" for appointments. */
    public const int UPCOMING_HOURS = 48;

    /** Fall of conversion (percentage points) that deserves attention. */
    public const int CONVERSION_DROP_POINTS = 10;

    /**
     * @param  array{conversion: int|null}  $current
     * @param  array{conversion: int|null}  $previous
     * @return list<array{key: string, tone: string, title: string, description: string, count: int, action: string, href: string}>
     */
    public function for(Tenant $tenant, array $current, array $previous): array
    {
        $tenantId = (int) $tenant->getKey();
        $requests = fn () => ServiceRequest::query()->forTenant($tenantId);
        $items = [];

        $waitingTriage = $requests()->where('status', RequestStatus::Requested->value)->count();

        if ($waitingTriage > 0) {
            $items[] = $this->item('triage', 'attention', $waitingTriage, $waitingTriage === 1 ? '1 solicitação aguardando triagem' : "{$waitingTriage} solicitações aguardando triagem",
                'Responda rápido: quem espera muito tende a procurar outro prestador.', 'Fazer a triagem', route('tenant.requests.index', ['status' => RequestStatus::Requested->value], false));
        }

        $waitingHuman = AssistantConversation::query()->forTenant($tenantId)->where('status', ConversationStatus::AwaitingHuman->value)->count();

        if ($waitingHuman > 0) {
            $items[] = $this->item('human', 'attention', $waitingHuman, $waitingHuman === 1 ? '1 conversa aguardando a equipe' : "{$waitingHuman} conversas aguardando a equipe",
                'O atendimento IA passou a conversa para uma pessoa da equipe.', 'Abrir o Atendimento IA', route('tenant.assistant.index', [], false));
        }

        $stalled = $requests()->stalled()->count();

        if ($stalled > 0) {
            $items[] = $this->item('stalled', 'attention', $stalled, $stalled === 1 ? '1 solicitação sem movimentação recente' : "{$stalled} solicitações sem movimentação recente",
                'Em aberto há mais de '.ServiceRequest::STALE_AFTER_DAYS.' dias sem nenhum registro no histórico.', 'Ver solicitações paradas', route('tenant.requests.index', ['parada' => 1], false));
        }

        $upcoming = Appointment::query()->forTenant($tenantId)
            ->where('status', AppointmentStatus::Scheduled->value)
            ->whereBetween('starts_at', [now(), now()->addHours(self::UPCOMING_HOURS)])
            ->count();

        if ($upcoming > 0) {
            $items[] = $this->item('upcoming', 'info', $upcoming, $upcoming === 1 ? '1 agendamento nas próximas 48 horas' : "{$upcoming} agendamentos nas próximas 48 horas",
                'Confirme com o cliente e com o profissional.', 'Abrir a agenda', route('tenant.agenda.index', ['view' => 'week'], false));
        }

        $withoutAmount = $requests()->where('status', RequestStatus::Completed->value)->whereNull('realized_amount_cents')->count();

        if ($withoutAmount > 0) {
            $items[] = $this->item('amount', 'info', $withoutAmount, $withoutAmount === 1 ? '1 serviço concluído sem valor registrado' : "{$withoutAmount} serviços concluídos sem valor registrado",
                'Sem o valor, a receita e o ticket médio ficam incompletos.', 'Informar os valores', route('tenant.requests.index', ['status' => RequestStatus::Completed->value, 'sem_valor' => 1], false));
        }

        if ($current['conversion'] !== null && $previous['conversion'] !== null && $previous['conversion'] - $current['conversion'] >= self::CONVERSION_DROP_POINTS) {
            $drop = $previous['conversion'] - $current['conversion'];
            $items[] = $this->item('conversion', 'warning', $drop, "Conversão caiu {$drop} pontos percentuais",
                "Passou de {$previous['conversion']}% para {$current['conversion']}% em relação ao período anterior. Veja os motivos de cancelamento e as origens.", 'Ver motivos', '#cancelamentos');
        }

        return $items;
    }

    /**
     * @return array{key: string, tone: string, title: string, description: string, count: int, action: string, href: string}
     */
    private function item(string $key, string $tone, int $count, string $title, string $description, string $action, string $href): array
    {
        return ['key' => $key, 'tone' => $tone, 'title' => $title, 'description' => $description, 'count' => $count, 'action' => $action, 'href' => $href];
    }
}
