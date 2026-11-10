<?php

namespace App\Services;

use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProfessionalAvailabilityService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private AvailabilityRules $rules,
    ) {}

    /**
     * Replace the full set of working windows of a professional, atomically.
     *
     * @param  list<array{weekday: int, start_time: string, end_time: string}>  $windows
     */
    public function replace(Professional $professional, array $windows, User $actor): Professional
    {
        $this->rules->ensureValid($windows);

        DB::transaction(function () use ($professional, $windows, $actor): void {
            ProfessionalAvailability::query()->where('professional_id', $professional->getKey())->delete();

            foreach ($windows as $window) {
                ProfessionalAvailability::create([
                    'tenant_id' => $professional->tenant_id,
                    'professional_id' => $professional->getKey(),
                    'weekday' => $window['weekday'],
                    'start_time' => $window['start_time'],
                    'end_time' => $window['end_time'],
                ]);
            }

            $this->auditLogger->record('professional.availability_updated', $professional->tenant, $professional, [
                'windows' => count($windows),
            ], $actor);
        });

        return $professional->refresh();
    }
}
