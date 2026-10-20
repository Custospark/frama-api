<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'business_type_id' => $this->business_type_id,
            'business_type' => $this->whenLoaded('businessType', fn () => $this->businessType ? [
                'id' => $this->businessType->id,
                'name' => $this->businessType->name,
                'slug' => $this->businessType->slug,
                'icon' => $this->businessType->icon,
            ] : null),
            'name' => $this->name,
            'unit_price' => $this->unit_price !== null ? (float) $this->unit_price : null,
            'is_active' => (bool) $this->is_active,
            'custom_values' => $this->presentCustomValues(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCustomValues(): array
    {
        $values = $this->custom_values ?? [];

        return collect($values)->map(function ($value) {
            if (is_string($value) && str_starts_with($value, 'assets/custom/')) {
                return Storage::disk('public')->url($value);
            }

            return $value;
        })->all();
    }
}
