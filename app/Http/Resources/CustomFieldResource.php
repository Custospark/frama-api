<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope?->value,
            'business_type_id' => $this->business_type_id,
            'business_type' => $this->whenLoaded('businessType', fn () => $this->businessType ? [
                'id' => $this->businessType->id,
                'name' => $this->businessType->name,
                'slug' => $this->businessType->slug,
            ] : null),
            'branch_id' => $this->branch_id,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'help_text' => $this->help_text,
            'placeholder' => $this->placeholder,
            'is_required' => (bool) $this->is_required,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'options' => $this->options ?? [],
            'validation' => $this->validation ?? (object) [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
