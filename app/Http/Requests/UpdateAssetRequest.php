<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldScope;
use App\Models\Asset;
use App\Services\DynamicFieldValidator;

class UpdateAssetRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'business_type_id' => ['sometimes', 'integer', 'exists:business_types,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $businessTypeId = $this->input('business_type_id')
            ?? Asset::find($this->route('asset'))?->business_type_id;

        if ($businessTypeId) {
            $definitions = app(DynamicFieldValidator::class)
                ->definitions(CustomFieldScope::Asset, (int) $businessTypeId, $this->user()?->branch_id);

            // On update, fields are only validated when present (not forced required).
            foreach ($definitions as $field) {
                $fieldRules = app(DynamicFieldValidator::class)
                    ->rules(collect([$field]))["custom_values.{$field->key}"];
                $fieldRules[0] = 'nullable';
                $rules["custom_values.{$field->key}"] = $fieldRules;
            }
        }

        return $rules;
    }
}
