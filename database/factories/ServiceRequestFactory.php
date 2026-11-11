<?php

namespace Database\Factories;

use App\Enums\RequestOrigin;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'service_id' => null,
            'professional_id' => null,
            'description' => fake()->sentence(),
            'origin' => RequestOrigin::Panel,
            'priority' => RequestPriority::Normal,
            'status' => RequestStatus::Requested,
            'closing_reason' => null,
            'created_by_user_id' => null,
            'closed_at' => null,
        ];
    }
}
