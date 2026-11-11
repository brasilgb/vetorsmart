<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(3)->setTime(9, 0);

        return [
            'tenant_id' => Tenant::factory(),
            'service_request_id' => ServiceRequest::factory(),
            'professional_id' => Professional::factory(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->addHour(),
            'status' => AppointmentStatus::Scheduled,
            'cancelling_reason' => null,
            'created_by_user_id' => null,
        ];
    }
}
