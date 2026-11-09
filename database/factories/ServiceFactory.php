<?php

namespace Database\Factories;

use App\Enums\PriceType;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'service_category_id' => null,
            'name' => fake()->unique()->lexify('Serviço ??????'),
            'description' => null,
            'duration_minutes' => null,
            'price' => null,
            'price_type' => PriceType::OnRequest,
            'requires_evaluation' => false,
            'active' => true,
            'sort_order' => 0,
        ];
    }

    public function fixed(string $price = '150.00'): static
    {
        return $this->state(fn (array $attributes) => ['price_type' => PriceType::Fixed, 'price' => $price]);
    }

    public function startingAt(string $price = '80.00'): static
    {
        return $this->state(fn (array $attributes) => ['price_type' => PriceType::StartingAt, 'price' => $price]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
