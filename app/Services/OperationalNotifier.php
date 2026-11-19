<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\NotificationCategory;
use App\Enums\TenantRole;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\AssistantConversation;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\OperationalMail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Operational notices of a company: a notice in the panel for each active member, an e-mail copy when the member
 * keeps that category on, and an e-mail to the customer for the events that concern them.
 *
 * Every notice is prepared only after the surrounding transaction commits, so a rolled-back operation notifies
 * nobody. Each event has a key, claimed once in notification_deliveries, so a repeated event never notifies twice.
 * The person who caused the event is not notified about it.
 */
class OperationalNotifier
{
    /**
     * Setting key (tenant_settings.settings) that turns the e-mails to customers off. Absent means on.
     */
    public const string CUSTOMER_EMAILS_SETTING = 'customer_emails';

    /**
     * A new service request, from the panel, the public site or the assistant.
     */
    public function requestReceived(ServiceRequest $request, ?User $actor): void
    {
        $this->withActor($actor)->afterCommit("request.received:{$request->getKey()}", $request->tenant_id, function () use ($request, $actor): void {
            $request->loadMissing(['tenant.settings', 'customer']);
            $tenant = $request->tenant;
            $customer = $request->customer;
            $path = route('tenant.requests.show', ['serviceRequest' => $request->getKey()], false);

            $this->notifyTeam($tenant, NotificationCategory::RequestReceived, $actor,
                title: 'Nova solicitação de '.$this->name($customer),
                body: str($request->description)->limit(160)->value(),
                path: $path,
            );

            $this->notifyCustomer($tenant, $customer,
                subject: 'Recebemos sua solicitação',
                lines: [
                    $this->businessName($tenant).' recebeu sua solicitação e vai entrar em contato para combinar o atendimento.',
                    'Pedido: '.str($request->description)->limit(200)->value(),
                ],
            );
        });
    }

    /**
     * An appointment booked or moved to a new time.
     */
    public function appointmentConfirmed(Appointment $appointment, ?User $actor): void
    {
        $key = "appointment.confirmed:{$appointment->getKey()}:{$appointment->starts_at->getTimestamp()}";

        $this->withActor($actor)->afterCommit($key, $appointment->tenant_id, function () use ($appointment, $actor): void {
            $appointment->loadMissing(['tenant.settings', 'serviceRequest.customer', 'serviceRequest.service', 'professional']);
            $tenant = $appointment->tenant;
            $when = $tenant->toLocal($appointment->starts_at);
            $service = $appointment->serviceRequest->service->name ?? 'Atendimento';
            $customer = $appointment->serviceRequest?->customer;

            $this->notifyTeam($tenant, NotificationCategory::AppointmentConfirmed, $actor,
                title: 'Agendamento em '.$when->format('d/m/Y \à\s H:i'),
                body: $service.' · '.$this->name($customer).($appointment->professional ? ' · '.$appointment->professional->name : ''),
                path: route('tenant.agenda.index', ['view' => 'day', 'date' => $when->format('Y-m-d')], false),
            );

            $this->notifyCustomer($tenant, $customer,
                subject: 'Agendamento confirmado: '.$when->format('d/m/Y \à\s H:i'),
                lines: [
                    'Seu atendimento com '.$this->businessName($tenant).' está confirmado.',
                    'Serviço: '.$service.'.',
                    'Data e horário: '.$when->format('d/m/Y \à\s H:i').' (horário local da empresa).',
                ],
            );
        });
    }

    /**
     * An appointment cancelled, directly in the agenda or with its request.
     */
    public function appointmentCancelled(Appointment $appointment, ?User $actor): void
    {
        $this->withActor($actor)->afterCommit("appointment.cancelled:{$appointment->getKey()}", $appointment->tenant_id, function () use ($appointment, $actor): void {
            $appointment->loadMissing(['tenant.settings', 'serviceRequest.customer', 'serviceRequest.service']);
            $tenant = $appointment->tenant;
            $when = $tenant->toLocal($appointment->starts_at);
            $service = $appointment->serviceRequest->service->name ?? 'Atendimento';
            $customer = $appointment->serviceRequest?->customer;

            $this->notifyTeam($tenant, NotificationCategory::AppointmentCancelled, $actor,
                title: 'Agendamento cancelado ('.$when->format('d/m/Y H:i').')',
                body: $service.' · '.$this->name($customer),
                path: route('tenant.requests.show', ['serviceRequest' => $appointment->service_request_id], false),
            );

            $this->notifyCustomer($tenant, $customer,
                subject: 'Agendamento cancelado',
                lines: [
                    'O atendimento de '.$when->format('d/m/Y \à\s H:i').' com '.$this->businessName($tenant).' foi cancelado.',
                    'Serviço: '.$service.'.',
                    'Para remarcar, fale com a empresa.',
                ],
            );
        });
    }

    /**
     * A request concluded: only the customer is told, so the team is not notified about its own work.
     */
    public function serviceCompleted(ServiceRequest $request): void
    {
        $this->afterCommit("request.completed:{$request->getKey()}", $request->tenant_id, function () use ($request): void {
            $request->loadMissing(['tenant.settings', 'customer', 'service']);

            $this->notifyCustomer($request->tenant, $request->customer,
                subject: 'Atendimento concluído',
                lines: [
                    $this->businessName($request->tenant).' registrou a conclusão do seu atendimento'.($request->service ? ' ('.$request->service->name.')' : '').'.',
                    'Obrigado pela confiança.',
                ],
            );
        });
    }

    /**
     * A visitor conversation handed over to the team. The key includes the size of the conversation, so the same
     * hand-over is notified once, and a later one, after the conversation went back to the assistant, again.
     */
    public function awaitingAttendance(AssistantConversation $conversation): void
    {
        $key = "assistant.awaiting:{$conversation->getKey()}:{$conversation->messages()->count()}";

        $this->afterCommit($key, $conversation->tenant_id, function () use ($conversation): void {
            $conversation->loadMissing(['tenant.settings']);

            $this->notifyTeam($conversation->tenant, NotificationCategory::AwaitingAttendance, null,
                title: 'Conversa do site aguardando atendimento',
                body: $conversation->handoff_reason,
                path: route('tenant.assistant.index', ['conversation' => $conversation->getKey()], false),
            );
        });
    }

    /**
     * A company suspended or reactivated by the platform. Owners are always told by e-mail, even while suspended.
     */
    public function tenantStatusChanged(Tenant $tenant, bool $active): void
    {
        $key = 'tenant.status:'.$tenant->getKey().':'.($active ? 'active' : 'suspended').':'.now()->format('YmdHis');

        $this->afterCommit($key, $tenant->getKey(), function () use ($tenant, $active): void {
            $this->notifyTeam($tenant, NotificationCategory::AdminNotice, null,
                title: $active ? 'Acesso da empresa reativado' : 'Acesso da empresa suspenso',
                body: $active
                    ? 'A administração da VetorSmart reativou o acesso de '.$tenant->name.'.'
                    : 'A administração da VetorSmart suspendeu o acesso de '.$tenant->name.'. Fale com o suporte para regularizar.',
                path: null,
                roles: [TenantRole::Owner],
            );
        });
    }

    /**
     * A billing notice to the company's owners (trial ending, payment pending, financial suspension, reactivation).
     * Administrative and therefore always sent by e-mail. The key makes each notice happen once.
     */
    /**
     * The platform team answered a support request publicly: owners and admins of the company get a notice in the panel
     * and an e-mail. Only the number, the subject and the link go out, never the content of the answer. Once per answer
     * (the event id is the key); a failure is logged and never blocks the request.
     */
    public function supportAnswered(SupportTicket $ticket, int $eventId, User $actor): void
    {
        $tenant = $ticket->tenant;

        if ($tenant === null) {
            return;
        }

        $this->afterCommit("support.answered:{$eventId}", $tenant->getKey(), function () use ($ticket, $tenant, $actor): void {
            try {
                $tenant->loadMissing('settings');
                $this->notifyTeam($tenant, NotificationCategory::AdminNotice, $actor,
                    title: "Resposta da equipe VetorSmart no chamado #{$ticket->getKey()}",
                    body: 'Assunto: '.$ticket->subject.'. Abra o chamado no painel para ler a resposta.',
                    path: route('tenant.support.show', ['ticket' => $ticket->getKey()], false),
                    roles: [TenantRole::Owner, TenantRole::Admin],
                );
            } catch (Throwable $exception) {
                Log::warning('Aviso de resposta de suporte não enviado.', ['ticket_id' => $ticket->getKey(), 'error' => $exception->getMessage()]);
            }
        });
    }

    /**
     * The company answered a request that was waiting for it (or reopened a resolved one): the person responsible is
     * warned by e-mail, or every active platform administrator when nobody is assigned. Once per answer; a failure is
     * logged and never blocks the request.
     */
    public function supportCompanyReplied(SupportTicket $ticket, int $eventId): void
    {
        $this->afterCommit("support.company_replied:{$eventId}", $ticket->tenant_id, function () use ($ticket): void {
            try {
                $assignee = $ticket->assignee;
                $recipients = $assignee !== null && $assignee->isRootAdmin()
                    ? collect([$assignee])
                    : User::query()->where('is_root_admin', true)->where('status', UserStatus::Active->value)->get();

                $recipients = $recipients->filter(fn (User $user): bool => $user->hasVerifiedEmail());

                if ($recipients->isEmpty()) {
                    return;
                }

                Notification::send($recipients, new OperationalMail(
                    subjectLine: "Empresa respondeu o chamado #{$ticket->getKey()} · VetorSmart",
                    heading: "A empresa respondeu o chamado #{$ticket->getKey()}",
                    lines: ['Empresa: '.($ticket->tenant->name ?? '—').'.', 'Assunto: '.$ticket->subject.'.', 'O chamado voltou para a equipe.'],
                    actionText: 'Abrir no RootAdmin',
                    actionUrl: config('platform.public_url').route('rootadmin.support.show', ['ticket' => $ticket->getKey()], false),
                    closing: 'VetorSmart · Central de Suporte.',
                ));
            } catch (Throwable $exception) {
                Log::warning('Aviso de resposta da empresa não enviado.', ['ticket_id' => $ticket->getKey(), 'error' => $exception->getMessage()]);
            }
        });
    }

    public function billingNotice(Tenant $tenant, string $key, string $title, string $body): void
    {
        $this->afterCommit('billing:'.$key, $tenant->getKey(), function () use ($tenant, $title, $body): void {
            $tenant->loadMissing('settings');

            $this->notifyTeam($tenant, NotificationCategory::AdminNotice, null,
                title: $title,
                body: $body,
                path: route('tenant.subscription.show', [], false),
                roles: [TenantRole::Owner],
            );
        });
    }

    /**
     * Events whose failed notice can be rebuilt and processed again (see reprocess()).
     *
     * @var list<string>
     */
    public const array REPROCESSABLE = ['request.received', 'request.assigned', 'request.completed', 'appointment.confirmed', 'appointment.cancelled'];

    /**
     * Author of the next event, kept with its delivery so a reprocessed notice still skips that person.
     */
    private ?int $nextActorId = null;

    /**
     * Key of the notice being processed, carried by its e-mails so their real outcome is recorded on the same row.
     */
    private ?string $currentKey = null;

    /**
     * The person who caused the next event (read once, by the next afterCommit call).
     */
    private function withActor(?User $actor): static
    {
        $this->nextActorId = $actor?->getKey();

        return $this;
    }

    /**
     * Claim the event key once the transaction commits, then run the notifier. A key already processed (or being
     * processed) is skipped; a key whose processing failed is claimed again, so a reprocessed notice runs once.
     *
     * A notice is a consequence of an operation that is already saved: when it fails (queue or mail unavailable, a
     * missing relation), the row is marked failed with the class of the error, the failure is logged without personal
     * data, and nothing reaches the caller. Otherwise a visitor would see an error for a request that was registered,
     * and a second send could duplicate it.
     */
    private function afterCommit(string $key, ?int $tenantId, callable $notify): void
    {
        $actorId = $this->nextActorId;
        $this->nextActorId = null;

        DB::afterCommit(function () use ($key, $tenantId, $notify, $actorId): void {
            try {
                if (! $this->claim($key, $tenantId, $actorId)) {
                    return;
                }
            } catch (Throwable $exception) {
                Log::warning('Operational notice not delivered.', ['key' => $key, 'tenant_id' => $tenantId, 'error' => $exception::class]);

                return;
            }

            $this->currentKey = $key;

            try {
                $notify();
                DB::table('notification_deliveries')->where('dedupe_key', $key)->update(['status' => 'processed', 'processed_at' => now(), 'last_error' => null, 'updated_at' => now()]);
            } catch (Throwable $exception) {
                DB::table('notification_deliveries')->where('dedupe_key', $key)->update(['status' => 'failed', 'last_error' => str($exception::class)->limit(120, '')->value(), 'updated_at' => now()]);
                Log::warning('Operational notice not delivered.', ['key' => $key, 'tenant_id' => $tenantId, 'error' => $exception::class]);
            } finally {
                $this->currentKey = null;
            }
        });
    }

    /**
     * Take the key for processing: a new key is inserted as "processing"; a failed key moves back to "processing" in a
     * single conditional update, so two attempts never run the same notice at once.
     */
    private function claim(string $key, ?int $tenantId, ?int $actorId): bool
    {
        $event = str($key)->before(':')->value();
        $subject = str($key)->after(':')->before(':')->value();

        $inserted = DB::table('notification_deliveries')->insertOrIgnore([
            'dedupe_key' => $key,
            'tenant_id' => $tenantId,
            'status' => 'processing',
            'event' => str($event)->limit(40, '')->value(),
            'subject_id' => ctype_digit($subject) ? (int) $subject : null,
            'actor_id' => $actorId,
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 1) {
            return true;
        }

        return DB::table('notification_deliveries')
            ->where('dedupe_key', $key)
            ->where('status', 'failed')
            ->update(['status' => 'processing', 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]) === 1;
    }

    /**
     * Process a failed notice again, rebuilding it from its event and subject. Returns false when it cannot be rebuilt
     * (event not reprocessable, subject gone, or the event changed since: an appointment moved to another time has a
     * notice of its own).
     */
    public function reprocess(string $key, ?string $event, ?int $subjectId, ?int $actorId): bool
    {
        $actor = $actorId === null ? null : User::query()->find($actorId);
        $id = $subjectId;

        $call = match ($event) {
            'request.received' => ($request = ServiceRequest::query()->find($id)) === null ? null : fn () => $this->requestReceived($request, $actor),
            'request.completed' => ($request = ServiceRequest::query()->find($id)) === null ? null : fn () => $this->serviceCompleted($request),
            // Only while that assignment is still the latest one: a later transfer has a notice of its own.
            'request.assigned' => ($request = ServiceRequest::query()->find($id)) === null || $request->assigned_user_id === null || ($assignee = User::query()->find($request->assigned_user_id)) === null
                || (int) $request->events()->where('type', 'assigned')->max('id') !== (int) str($key)->afterLast(':')->value()
                ? null
                : fn () => $this->requestAssigned($request, $assignee, (int) str($key)->afterLast(':')->value(), $actor),
            'appointment.confirmed' => ($appointment = Appointment::query()->find($id)) === null || "appointment.confirmed:{$appointment->getKey()}:{$appointment->starts_at->getTimestamp()}" !== $key
                ? null
                : fn () => $this->appointmentConfirmed($appointment, $actor),
            'appointment.cancelled' => ($appointment = Appointment::query()->find($id)) === null ? null : fn () => $this->appointmentCancelled($appointment, $actor),
            default => null,
        };

        if ($call === null) {
            DB::table('notification_deliveries')->where('dedupe_key', $key)->where('status', 'failed')->update(['status' => 'skipped', 'updated_at' => now()]);

            return false;
        }

        $call();

        return true;
    }

    /**
     * The request was assigned to a member of the company: only that person is told, never the author of the change.
     */
    public function requestAssigned(ServiceRequest $request, User $assignee, int $eventId, ?User $actor): void
    {
        if ($actor !== null && $actor->getKey() === $assignee->getKey()) {
            return;
        }

        $this->withActor($actor)->afterCommit("request.assigned:{$request->getKey()}:{$eventId}", $request->tenant_id, function () use ($request, $assignee): void {
            $request->loadMissing(['tenant.settings', 'customer']);
            $membership = TenantUser::query()->forTenant($request->tenant_id)->where('user_id', $assignee->getKey())->where('status', MembershipStatus::Active)->with('user')->first();

            if ($membership === null) {
                return;
            }

            $this->notifyMembers($request->tenant, collect([$membership]), NotificationCategory::RequestAssigned,
                title: 'Solicitação #'.$request->getKey().' atribuída a você',
                body: 'Cliente: '.$this->name($request->customer).'.',
                path: route('tenant.requests.show', ['serviceRequest' => $request->getKey()], false),
            );
        });
    }

    /**
     * A notice in the panel for every active member of the company (optionally only some roles), except the person
     * who caused the event, and an e-mail copy for those who keep the category on and confirmed their address.
     *
     * @param  list<TenantRole>|null  $roles
     */
    private function notifyTeam(Tenant $tenant, NotificationCategory $category, ?User $actor, string $title, ?string $body, ?string $path, ?array $roles = null): void
    {
        $this->notifyMembers($tenant, $this->members($tenant, $actor, $roles), $category, $title, $body, $path);
    }

    /**
     * A notice in the panel for each of these members, and the e-mail copy for those who want it.
     *
     * @param  Collection<int, TenantUser>  $members
     */
    private function notifyMembers(Tenant $tenant, Collection $members, NotificationCategory $category, string $title, ?string $body, ?string $path): void
    {
        foreach ($members as $membership) {
            TenantNotification::create([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $membership->user_id,
                'category' => $category,
                'title' => str($title)->limit(160, '')->value(),
                'body' => $body === null ? null : str($body)->limit(500, '')->value(),
                'url' => $path,
            ]);

            $user = $membership->user;

            if ($user === null || ! $user->hasVerifiedEmail() || ! $this->wantsEmail($membership, $category)) {
                continue;
            }

            $user->notify($this->tracked(new OperationalMail(
                subjectLine: $title.' · '.$this->businessName($tenant),
                heading: $title,
                lines: array_values(array_filter([$body, 'Empresa: '.$this->businessName($tenant).'.'])),
                actionText: $path === null ? null : 'Abrir no painel',
                actionUrl: $path === null ? null : config('platform.public_url').$path,
                closing: 'VetorSmart · Para deixar de receber este tipo de e-mail, ajuste as preferências em Notificações no painel.',
            )));
        }
    }

    /**
     * An e-mail to the customer, when the customer has an address and the company keeps customer e-mails on. It goes
     * out in the company's name, and replies reach the company's own address.
     *
     * @param  list<string>  $lines
     */
    private function notifyCustomer(Tenant $tenant, ?Customer $customer, string $subject, array $lines): void
    {
        if ($customer === null || blank($customer->email) || ! $this->customerEmailsEnabled($tenant)) {
            return;
        }

        $business = $this->businessName($tenant);
        $contact = collect([$tenant->phone, $tenant->settings?->whatsapp ? 'WhatsApp '.$tenant->settings->whatsapp : null, $tenant->email])->filter()->implode(' · ');

        Notification::route('mail', [(string) $customer->email => $customer->name])->notify($this->tracked(new OperationalMail(
            subjectLine: $subject.' · '.$business,
            heading: 'Olá, '.str($customer->name)->before(' ')->value().'!',
            lines: $contact === '' ? $lines : [...$lines, 'Contato da empresa: '.$contact.'.'],
            senderName: $business,
            replyTo: filled($tenant->email) ? $tenant->email : null,
            closing: $business.' · Mensagem enviada pela VetorSmart em nome da empresa.',
        )));
    }

    /**
     * Tie an e-mail to the notice being processed: it is counted as queued now, and its real outcome (sent or failed)
     * is recorded on the same row by the mail channel events.
     */
    private function tracked(OperationalMail $mail): OperationalMail
    {
        if ($this->currentKey !== null) {
            $mail->deliveryKey = $this->currentKey;
            DB::table('notification_deliveries')->where('dedupe_key', $this->currentKey)->increment('mail_queued');
        }

        return $mail;
    }

    /**
     * @param  list<TenantRole>|null  $roles
     * @return Collection<int, TenantUser>
     */
    private function members(Tenant $tenant, ?User $actor, ?array $roles): Collection
    {
        return TenantUser::query()
            ->forTenant($tenant)
            ->where('status', MembershipStatus::Active)
            ->when($roles !== null, fn ($query) => $query->whereIn('role', array_map(fn (TenantRole $role): string => $role->value, $roles ?? [])))
            ->when($actor !== null, fn ($query) => $query->where('user_id', '!=', $actor?->getKey()))
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::Active))
            ->with('user')
            ->get();
    }

    private function wantsEmail(TenantUser $membership, NotificationCategory $category): bool
    {
        return $category->isMandatory() || ($membership->notification_preferences[$category->value] ?? true) !== false;
    }

    private function customerEmailsEnabled(Tenant $tenant): bool
    {
        $settings = $tenant->settings;

        return ! $settings instanceof TenantSetting || ($settings->settings[self::CUSTOMER_EMAILS_SETTING] ?? true) !== false;
    }

    private function businessName(Tenant $tenant): string
    {
        return $tenant->settings?->business_name ?: $tenant->name;
    }

    private function name(?Customer $customer): string
    {
        return $customer->name ?? 'cliente';
    }
}
