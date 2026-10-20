<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseFormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['sometimes', Rule::in(Role::values())],
            'access_code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('users', 'access_code')->ignore($this->route('user'))],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'pin' => ['nullable', 'string', 'digits_between:4,6'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
