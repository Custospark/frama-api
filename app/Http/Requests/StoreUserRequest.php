<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Validation\Rule;

class StoreUserRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('access_code'))) {
            $this->merge([
                'access_code' => strtoupper(trim($this->input('access_code'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['required', Rule::in(Role::values())],
            'access_code' => ['required_unless:role,manager', 'nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:users,access_code'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'pin' => ['required', 'string', 'digits_between:4,6'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
