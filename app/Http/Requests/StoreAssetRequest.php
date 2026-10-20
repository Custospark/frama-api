<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldScope;
use App\Services\DynamicFieldValidator;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'business_type_id' => ['required', 'integer', 'exists:business_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $businessTypeId = $this->input('business_type_id');
        if ($businessTypeId) {
            $definitions = app(DynamicFieldValidator::class)
                ->definitions(CustomFieldScope::Asset, (int) $businessTypeId, $this->user()?->branch_id);
            $rules = array_merge($rules, app(DynamicFieldValidator::class)->rules($definitions));
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['business_type_id' => 'business type', 'name' => 'property name'];
    }
}
