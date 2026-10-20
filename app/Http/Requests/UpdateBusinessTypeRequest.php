<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateBusinessTypeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('business_type');

        return [
            'name' => ['sometimes', 'string', 'max:80', Rule::unique('business_types', 'name')->ignore($id)],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('business_types', 'slug')->ignore($id)],
            'icon' => ['nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
