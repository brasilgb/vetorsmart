<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Phone and timezone are read from the tenant record, so they are not duplicated.
 *
 * @mixin \App\Models\TenantSetting
 */
class TenantSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'business_name' => $this->business_name,
            'description' => $this->description,
            'phone' => $this->tenant?->phone,
            'timezone' => $this->tenant?->timezone,
            'whatsapp' => $this->whatsapp,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'locale' => $this->locale,
            'service_modes' => $this->service_modes ?? [],
            'welcome_message' => $this->welcome_message,
            'confirmation_policy' => $this->confirmation_policy->value,
            'settings' => $this->settings ?? [],
        ];
    }
}
