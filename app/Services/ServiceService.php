<?php

namespace App\Services;

use App\Enums\GrowthEventType;
use App\Enums\PriceType;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ServiceService
{
    public function __construct(private AuditLogger $auditLogger, private GrowthEventRecorder $growth) {}

    /**
     * Create a service. Only the name is required.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(Tenant $tenant, array $attributes, User $actor): Service
    {
        $data = $this->applyPricingRules($attributes, null);

        $service = Service::create([
            ...$data,
            'tenant_id' => $tenant->getKey(),
        ]);

        $service->refresh();

        $this->growth->record($tenant, GrowthEventType::ServiceCreated, $service);

        $this->auditLogger->record('service.created', $tenant, $service, [
            'fields' => array_keys($data),
        ], $actor);

        return $service;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Service $service, array $attributes, User $actor): Service
    {
        $data = $this->applyPricingRules($attributes, $service);

        $service->fill($data);
        $changedFields = array_keys($service->getDirty());
        $service->save();

        if ($changedFields !== []) {
            $this->auditLogger->record('service.updated', $service->tenant, $service, [
                'fields' => $changedFields,
            ], $actor);
        }

        return $service;
    }

    /**
     * Replace the set of professionals enabled for a service. Only active professionals of the
     * same company can be linked; the rest of the company's links are left untouched.
     *
     * @param  list<int>  $professionalIds
     */
    public function syncProfessionals(Service $service, array $professionalIds, User $actor): Service
    {
        $tenant = $service->tenant;
        $professionalIds = array_values(array_unique(array_map('intval', $professionalIds)));

        $found = Professional::query()->forTenant($tenant)->whereIn('id', $professionalIds)->where('active', true)->pluck('id')->all();

        if (count($found) !== count($professionalIds)) {
            throw ValidationException::withMessages([
                'professional_ids' => ['Um ou mais profissionais não existem nesta empresa ou estão inativos.'],
            ]);
        }

        $before = $service->professionals()->pluck('professionals.id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $service->professionals()->sync(array_fill_keys($professionalIds, ['tenant_id' => $tenant->getKey()]));

        $after = collect($professionalIds)->sort()->values()->all();

        if ($before !== $after) {
            $this->auditLogger->record('service.professionals_synced', $tenant, $service, [
                'added' => array_values(array_diff($after, $before)),
                'removed' => array_values(array_diff($before, $after)),
            ], $actor);
        }

        return $service;
    }

    /**
     * Deactivate a service. It keeps its history and stops accepting new appointments.
     */
    public function deactivate(Service $service, User $actor): Service
    {
        if (! $service->active) {
            return $service;
        }

        $service->active = false;
        $service->save();

        $this->auditLogger->record('service.deactivated', $service->tenant, $service, [], $actor);

        return $service;
    }

    /**
     * Enforce the price rules:
     * - fixed and starting_at require an amount;
     * - on_request must not carry an amount;
     * - the category, when given, must be active and belong to the same tenant.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function applyPricingRules(array $attributes, ?Service $current): array
    {
        $priceType = $attributes['price_type'] ?? ($current !== null ? $current->price_type : PriceType::OnRequest);
        $priceType = $priceType instanceof PriceType ? $priceType : PriceType::from((string) $priceType);

        $price = array_key_exists('price', $attributes)
            ? $attributes['price']
            : ($priceType->requiresAmount() ? $current?->price : null);

        if ($priceType->requiresAmount() && ($price === null || $price === '')) {
            throw ValidationException::withMessages([
                'price' => ['Informe o valor para este tipo de preço.'],
            ]);
        }

        if (! $priceType->requiresAmount() && $price !== null && $price !== '') {
            throw ValidationException::withMessages([
                'price' => ['Serviços com preço sob consulta não podem ter valor informado.'],
            ]);
        }

        $attributes['price_type'] = $priceType;
        $attributes['price'] = $priceType->requiresAmount() ? $price : null;

        return $attributes;
    }
}
