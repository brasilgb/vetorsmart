<?php

namespace App\Services;

use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfessionalRecordService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Tenant $tenant, array $attributes, User $actor): Professional
    {
        $attributes = $this->normalize($attributes);

        $professional = Professional::create([
            ...$attributes,
            'tenant_id' => $tenant->getKey(),
        ]);

        $professional->refresh();

        $this->auditLogger->record('professional.created', $tenant, $professional, [
            'fields' => array_keys($attributes),
        ], $actor);

        return $professional;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Professional $professional, array $attributes, User $actor): Professional
    {
        $attributes = $this->normalize($attributes);

        $professional->fill($attributes);
        $changedFields = array_keys($professional->getDirty());
        $professional->save();

        if ($changedFields !== []) {
            $this->auditLogger->record('professional.updated', $professional->tenant, $professional, [
                'fields' => $changedFields,
            ], $actor);
        }

        return $professional;
    }

    /**
     * Activate or deactivate a professional. Inactive professionals keep their history.
     */
    public function setActive(Professional $professional, bool $active, User $actor): Professional
    {
        if ($professional->active === $active) {
            return $professional;
        }

        $professional->active = $active;
        $professional->save();

        $this->auditLogger->record(
            $active ? 'professional.activated' : 'professional.deactivated',
            $professional->tenant,
            $professional,
            [],
            $actor,
        );

        return $professional;
    }

    /**
     * Replace the set of services a professional performs.
     *
     * Every service must belong to the same tenant. New links require an active
     * service; existing links are kept even if the service was later deactivated.
     *
     * @param  list<int>  $serviceIds
     *
     * @throws ValidationException
     */
    public function syncServices(Professional $professional, array $serviceIds, User $actor): Professional
    {
        $tenant = $professional->tenant;
        $serviceIds = array_values(array_unique($serviceIds));

        $found = Service::query()->forTenant($tenant)->whereIn('id', $serviceIds)->get();

        if ($found->count() !== count($serviceIds)) {
            throw ValidationException::withMessages([
                'service_ids' => ['Um ou mais serviços não existem nesta empresa.'],
            ]);
        }

        $currentIds = $professional->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all();
        $newIds = array_values(array_diff($serviceIds, $currentIds));
        $inactiveNew = $found->whereIn('id', $newIds)->where('active', false);

        if ($inactiveNew->isNotEmpty()) {
            throw ValidationException::withMessages([
                'service_ids' => ['Serviços inativos não podem ser vinculados a profissionais.'],
            ]);
        }

        DB::transaction(function () use ($professional, $serviceIds, $tenant, $currentIds, $actor): void {
            $payload = [];

            foreach ($serviceIds as $serviceId) {
                $payload[$serviceId] = ['tenant_id' => $tenant->getKey()];
            }

            $changes = $professional->services()->sync($payload);

            $this->auditLogger->record('professional.services_synced', $tenant, $professional, [
                'added' => array_map('intval', $changes['attached']),
                'removed' => array_map('intval', $changes['detached']),
                'previous_count' => count($currentIds),
            ], $actor);
        });

        return $professional->refresh();
    }

    /**
     * Store the phone as digits and the e-mail in lowercase, so searches match.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        if (array_key_exists('phone', $attributes)) {
            $attributes['phone'] = $attributes['phone'] === null ? null : (preg_replace('/\D+/', '', (string) $attributes['phone']) ?: null);
        }

        if (array_key_exists('email', $attributes)) {
            $attributes['email'] = $attributes['email'] === null ? null : mb_strtolower(trim((string) $attributes['email']));
        }

        return $attributes;
    }
}
