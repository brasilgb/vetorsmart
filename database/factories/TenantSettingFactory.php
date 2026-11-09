<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantSetting>
 */
class TenantSettingFactory extends Factory
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
            'business_name' => fake()->company(),
            'whatsapp' => null,
            'address' => null,
            'city' => null,
            'state' => null,
            'locale' => 'pt_BR',
            'settings' => [],
        ];
    }
}
