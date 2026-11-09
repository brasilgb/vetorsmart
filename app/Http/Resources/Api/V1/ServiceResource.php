<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prices are returned as decimal strings to avoid floating-point rounding.
 *
 * @mixin \App\Models\Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'service_category_id' => $this->service_category_id,
            'category' => new ServiceCategoryResource($this->whenLoaded('category')),
            'duration_minutes' => $this->duration_minutes,
            'price' => $this->price,
            'price_type' => $this->price_type->value,
            'requires_evaluation' => $this->requires_evaluation,
            'active' => $this->active,
            'sort_order' => $this->sort_order,
        ];
    }
}
