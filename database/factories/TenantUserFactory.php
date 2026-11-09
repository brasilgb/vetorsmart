<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantUser>
 */
class TenantUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'role' => TenantRole::Operator,
            'status' => MembershipStatus::Active,
        ];
    }

    /**
     * Indicate that the membership belongs to an owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => ['role' => TenantRole::Owner]);
    }

    /**
     * Indicate that the membership belongs to an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => TenantRole::Admin]);
    }

    /**
     * Indicate that the membership is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MembershipStatus::Inactive]);
    }
}
