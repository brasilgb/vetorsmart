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
 *   reactivate another) never creates a charge;
 * - more seats take effect at once and are charged from the next charge created; open charges are never changed and
 *   no proportional amount is charged (no approved commercial rule);
 * - fewer seats require the active users to fit; with a paid period running or a charge open they apply to the next
 *   period (the seats of the current period were paid), otherwise at once. While a reduction waits, activations
 *   respect the reduced limit, so it always fits when applied.
 *
 * A company without subscription, or a plan without included users, has no limit.
 */
class SeatService
{
    public function __construct(private AuditLogger $auditLogger) {}

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
        return DB::transaction(function () use ($subscription, $target, $actor): Subscription {
            Tenant::query()->whereKey($subscription->tenant_id)->lockForUpdate()->first();
            $subscription = Subscription::query()->with(['plan', 'tenant'])->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
            $included = $subscription->plan->included_users;

            if ($included === null) {
                throw ValidationException::withMessages(['extra_seats' => ['O plano não limita usuários: não há vagas adicionais a contratar.']]);
            }

            if (in_array($subscription->status, [SubscriptionStatus::Suspended, SubscriptionStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['extra_seats' => ['Regularize a assinatura antes de alterar as vagas.']]);
            }

            $current = $subscription->extra_seats;

            if ($target === $current) {
                if ($subscription->scheduled_extra_seats !== null) {
                    $subscription->forceFill(['scheduled_extra_seats' => null])->save();
                    $this->record($subscription, 'seats_change_withdrawn', $actor, "Mantidas {$current} vaga(s) adicional(is).");
                }

                return $subscription;
            }

            if ($target > $current) {
                $subscription->forceFill(['extra_seats' => $target, 'scheduled_extra_seats' => null])->save();
                $this->record($subscription, 'seats_added', $actor, "Vagas adicionais: {$current} → {$target}. Cobradas a partir da próxima cobrança gerada, sem valor proporcional.");
                $this->auditLogger->record('subscription.seats_changed', $subscription->tenant, $subscription, ['from' => $current, 'to' => $target, 'scheduled' => false], $actor);

                return $subscription;
            }

            $active = $this->activeUsers($subscription->tenant);

            if ($active > $included + $target) {
                throw ValidationException::withMessages(['extra_seats' => ["Há {$active} usuários ativos. Desative usuários até caberem em ".($included + $target).' vagas antes de reduzir.']]);
            }

            $paidPeriodRunning = $subscription->current_period_end?->isFuture() === true;
            $openCharge = BillingCharge::query()->where('subscription_id', $subscription->getKey())->whereIn('status', [ChargeStatus::Pending->value, ChargeStatus::Waiting->value])->exists();
            $scheduled = $paidPeriodRunning || $openCharge;

            $subscription->forceFill($scheduled ? ['scheduled_extra_seats' => $target] : ['extra_seats' => $target, 'scheduled_extra_seats' => null])->save();
            $this->record($subscription, $scheduled ? 'seats_reduction_scheduled' : 'seats_removed', $actor, $scheduled
                ? "Vagas adicionais: {$current} → {$target} a partir do próximo período."
                : "Vagas adicionais: {$current} → {$target}.");
            $this->auditLogger->record('subscription.seats_changed', $subscription->tenant, $subscription, ['from' => $current, 'to' => $target, 'scheduled' => $scheduled], $actor);

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
