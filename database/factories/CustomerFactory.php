<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Enums\PersonType;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'person_type' => PersonType::Individual,
            'document' => null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('119########'),
            'whatsapp' => null,
            'address' => null,
            'city' => null,
            'state' => null,
            'notes' => null,
            'status' => CustomerStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CustomerStatus::Inactive]);
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes) => ['person_type' => PersonType::Company]);
    }
}
