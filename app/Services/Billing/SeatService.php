<?php

namespace App\Services\Billing;

use App\Enums\BillingSource;
use App\Enums\ChargeStatus;
use App\Enums\MembershipStatus;
use App\Enums\SubscriptionStatus;
use App\Models\BillingCharge;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * User seats of a company: the included users of the plan plus the additional seats contracted. Registering users and
 * contracting seats are separate actions:
 *
 * - a user can be added or reactivated only while an active seat is free; replacing a user (deactivate one, add or
 *   reactivate another) never creates a charge, and inactive users are never charged;
 * - no complementary (proportional) charge exists yet, so a seat is never active before it is paid:
 *   - in the trial without an open charge, a change applies at once and the seats go into the first charge;
 *   - with a paid period running or a charge open, the change is scheduled (scheduled_extra_seats): the next charge
 *     created includes it, and the seats become active only when that charge is paid (SubscriptionService::
 *     periodPaid). Open charges and running contracts are never changed;
 * - fewer seats always require the active users to fit; while a reduction waits, activations respect it;
 * - with a payment pending, a suspension or a cancellation no change is accepted;
 * - asking again for the seats already scheduled changes nothing (idempotent).
 *
 * A company without subscription, or a plan without included users, has no limit.
 */
class SeatService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private BillingEnforcement $enforcement,
    ) {}

    /**
     * Active users allowed now, or null when there is no limit.
     */
    public function limit(Tenant $tenant): ?int
    {
        $subscription = Subscription::query()->forTenant($tenant)->with('plan')->first();

        if ($subscription === null || $subscription->plan->included_users === null) {
            return null;
        }

        $extra = $subscription->scheduled_extra_seats === null ? $subscription->extra_seats : min($subscription->extra_seats, $subscription->scheduled_extra_seats);

        return $subscription->plan->included_users + $extra;
    }

    public function activeUsers(Tenant $tenant): int
    {
        return TenantUser::query()->where('tenant_id', $tenant->getKey())->where('status', MembershipStatus::Active->value)->count();
    }

    /**
     * Refuse a new active user when every seat is taken. Must run inside the transaction that activates the user; the
     * tenant row lock serializes concurrent activations of the same company.
     */
    public function ensureSeatAvailable(Tenant $tenant): void
    {
        Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->first();
        $limit = $this->limit($tenant);

        if ($limit !== null && $this->activeUsers($tenant) >= $limit) {
            throw ValidationException::withMessages(['seats' => ["Todas as {$limit} vagas de usuário estão em uso. Desative um usuário ou contrate vagas adicionais em Minha Assinatura."]]);
        }
    }

    /**
     * Change the number of additional seats contracted. Returns the subscription with the change applied or scheduled.
     */
    public function changeExtraSeats(Subscription $subscription, int $target, User $actor): Subscription
    {
        if (! $this->enforcement->active()) {
            throw ValidationException::withMessages(['extra_seats' => ['A contratação de vagas adicionais será liberada quando a cobrança entrar em vigor.']]);
        }

        return DB::transaction(function () use ($subscription, $target, $actor): Subscription {
            Tenant::query()->whereKey($subscription->tenant_id)->lockForUpdate()->first();
            $subscription = Subscription::query()->with(['plan', 'tenant'])->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
            $included = $subscription->plan->included_users;

            if ($included === null) {
                throw ValidationException::withMessages(['extra_seats' => ['O plano não limita usuários: não há vagas adicionais a contratar.']]);
            }

            if (! in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
                throw ValidationException::withMessages(['extra_seats' => ['Regularize a assinatura (pagamento pendente, suspensão ou cancelamento) antes de alterar as vagas.']]);
            }

            $current = $subscription->extra_seats;
            $pending = $subscription->scheduled_extra_seats ?? $current;

            if ($target === $pending) {
                return $subscription;
            }

            if ($target === $current) {
                $subscription->forceFill(['scheduled_extra_seats' => null])->save();
                $this->record($subscription, 'seats_change_withdrawn', $actor, "Mantidas {$current} vaga(s) adicional(is).");

                return $subscription;
            }

            $active = $this->activeUsers($subscription->tenant);

            if ($target < $current && $active > $included + $target) {
                throw ValidationException::withMessages(['extra_seats' => ["Há {$active} usuários ativos. Desative usuários até caberem em ".($included + $target).' vagas antes de reduzir.']]);
            }

            $openCharge = BillingCharge::query()->where('subscription_id', $subscription->getKey())->whereIn('status', [ChargeStatus::Pending->value, ChargeStatus::Waiting->value])->exists();
            $immediate = $subscription->status === SubscriptionStatus::Trialing && ! $openCharge;

            $subscription->forceFill($immediate ? ['extra_seats' => $target, 'scheduled_extra_seats' => null] : ['scheduled_extra_seats' => $target])->save();

            $this->record($subscription, $immediate ? 'seats_changed' : 'seats_change_scheduled', $actor, $immediate
                ? "Vagas adicionais: {$current} → {$target}, incluídas na primeira cobrança."
                : "Vagas adicionais: {$current} → {$target} na próxima cobrança; ativas quando ela for paga, sem valor proporcional.");
            $this->auditLogger->record('subscription.seats_changed', $subscription->tenant, $subscription, ['from' => $current, 'to' => $target, 'scheduled' => ! $immediate], $actor);

            return $subscription;
        });
    }

    private function record(Subscription $subscription, string $type, User $actor, string $note): void
    {
        $event = new SubscriptionEvent;
        $event->forceFill([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->getKey(),
            'type' => $type,
            'from_status' => $subscription->status->value,
            'to_status' => $subscription->status->value,
            'source' => BillingSource::Company,
            'note' => $note,
            'user_id' => $actor->getKey(),
        ])->save();
    }
}
