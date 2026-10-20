<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use Illuminate\Validation\Rule;

class StoreCustomFieldRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(CustomFieldScope::values())],
            'business_type_id' => ['nullable', 'integer', 'exists:business_types,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'key' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(CustomFieldType::values())],
            'help_text' => ['nullable', 'string', 'max:500'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'options' => ['nullable', 'array'],
            'options.*.value' => ['required_with:options', 'string', 'max:120'],
            'options.*.label' => ['nullable', 'string', 'max:120'],
            'validation' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'business_type_id' => 'business type',
            'key' => 'field key',
            'label' => 'field label',
        ];
    }
}
