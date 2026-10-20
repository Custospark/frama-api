<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldType;
use Illuminate\Validation\Rule;

class UpdateCustomFieldRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(CustomFieldType::values())],
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
}
