<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreBusinessTypeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('business_types', 'name')],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('business_types', 'slug')],
            'icon' => ['nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
