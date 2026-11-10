<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalAvailability>
 */
class ProfessionalAvailabilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $professional = Professional::factory()->create();

        return [
            'tenant_id' => $professional->tenant_id,
            'professional_id' => $professional->id,
            'weekday' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ];
    }
}
