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

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['required', Rule::in(Role::values())],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'pin' => ['required', 'string', 'digits_between:4,6'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
