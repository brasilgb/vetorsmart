<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer records. Every query is limited to the tenant given by the caller.
 */
class CustomerService
{
    /**
     * @var list<string>
     */
    private const array FIELDS = ['name', 'person_type', 'document', 'email', 'phone', 'whatsapp', 'address', 'city', 'state', 'notes', 'status'];

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Create a customer. Only the name is required; the rest is optional.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(Tenant $tenant, array $attributes, User $actor): Customer
    {
        $data = $this->normalize($attributes);

        return DB::transaction(function () use ($tenant, $data, $actor): Customer {
            $this->ensureNoDuplicate($tenant, $data, null);

            $customer = Customer::create([
                ...$data,
                'tenant_id' => $tenant->getKey(),
                'status' => $data['status'] ?? CustomerStatus::Active,
            ]);

            $this->auditLogger->record('customer.created', $tenant, $customer, [
                'fields' => array_keys($data),
            ], $actor);

            return $customer;
        });
    }

    /**
     * Update a customer that already belongs to the tenant.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Customer $customer, array $attributes, User $actor): Customer
    {
        $data = $this->normalize($attributes);

        return DB::transaction(function () use ($customer, $data, $actor): Customer {
            $tenant = $customer->tenant;

            $this->ensureNoDuplicate($tenant, $data, $customer);

            $customer->fill($data);
            $changedFields = array_keys($customer->getDirty());
            $customer->save();

            if ($changedFields !== []) {
                $this->auditLogger->record('customer.updated', $tenant, $customer, [
                    'fields' => $changedFields,
                ], $actor);
            }

            return $customer;
        });
    }

    /**
     * Deactivate a customer. The record is kept so history stays intact.
     */
    public function deactivate(Customer $customer, User $actor): Customer
    {
        if ($customer->status === CustomerStatus::Inactive) {
            return $customer;
        }

        $customer->status = CustomerStatus::Inactive;
        $customer->save();

        $this->auditLogger->record('customer.deactivated', $customer->tenant, $customer, [], $actor);

        return $customer;
    }

    /**
     * Keep only known fields and store document and phone numbers as digits.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        $data = array_intersect_key($attributes, array_flip(self::FIELDS));

        foreach (['document', 'phone', 'whatsapp'] as $numeric) {
            if (array_key_exists($numeric, $data)) {
                $data[$numeric] = $data[$numeric] === null ? null : (preg_replace('/\D+/', '', (string) $data[$numeric]) ?: null);
            }
        }

        if (array_key_exists('email', $data)) {
            $data['email'] = $data['email'] === null ? null : mb_strtolower(trim((string) $data['email']));
        }

        return $data;
    }

    /**
     * Refuse a record that clearly duplicates an existing customer of the same tenant.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function ensureNoDuplicate(Tenant $tenant, array $data, ?Customer $current): void
    {
        $matches = Customer::query()
            ->forTenant($tenant)
            ->when($current, fn ($query) => $query->whereKeyNot($current->getKey()))
            ->where(function ($query) use ($data): void {
                $query->whereRaw('1 = 0');

                if (! empty($data['document'])) {
                    $query->orWhere('document', $data['document']);
                }

                if (! empty($data['email'])) {
                    $query->orWhere('email', $data['email']);
                }

                if (! empty($data['phone'])) {
                    $query->orWhere('phone', $data['phone'])->orWhere('whatsapp', $data['phone']);
                }

                if (! empty($data['whatsapp'])) {
                    $query->orWhere('phone', $data['whatsapp'])->orWhere('whatsapp', $data['whatsapp']);
                }
            })
            ->first();

        if ($matches !== null) {
            throw ValidationException::withMessages([
                'duplicate' => ["Já existe um cliente cadastrado com estes dados (#{$matches->getKey()})."],
            ]);
        }
    }
}
