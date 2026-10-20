<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_name' => $this->branch_name,
            'code' => $this->code,
            'slug' => $this->slug,
            'business_type_id' => $this->business_type_id,
            'business_type' => $this->whenLoaded('businessType', fn () => $this->businessType ? [
                'id' => $this->businessType->id,
                'name' => $this->businessType->name,
                'slug' => $this->businessType->slug,
            ] : null),
            'manager_name' => $this->manager_name,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'assets_count' => $this->whenCounted('assets'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
