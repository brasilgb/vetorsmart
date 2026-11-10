<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TenantSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Phone, timezone and segment are read from the tenant record, so they are not duplicated.
 *
 * @mixin TenantSetting
 */
class TenantSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $preferences = $this->settings ?? [];

        return [
            'business_name' => $this->business_name,
            'description' => $this->description,
            'segment_id' => $this->tenant?->segment_id,
            'phone' => $this->tenant?->phone,
            'timezone' => $this->tenant?->timezone,
            'whatsapp' => $this->whatsapp,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'locale' => $this->locale,
            'brand_color' => $this->brand_color,
            'business_hours' => $this->business_hours,
            'service_modes' => $this->service_modes ?? [],
            'welcome_message' => $this->welcome_message,
            'confirmation_policy' => $this->confirmation_policy->value,
            'currency' => $preferences['currency'] ?? 'BRL',
            'date_format' => $preferences['date_format'] ?? 'd/m/Y',
            'settings' => $preferences,
        ];
    }
}
