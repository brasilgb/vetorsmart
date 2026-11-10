<?php

namespace App\Services;

use App\Enums\GrowthEventType;
use App\Models\GrowthEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Records one event per real business action. Only the event type and a reference to
 * the record are stored, never personal data copied from the record.
 */
class GrowthEventRecorder
{
    public function record(Tenant $tenant, GrowthEventType $type, ?Model $subject = null): GrowthEvent
    {
        return GrowthEvent::create([
            'tenant_id' => $tenant->getKey(),
            'event_type' => $type,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'occurred_at' => now(),
        ]);
    }
}
