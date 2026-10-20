<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreBranchRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32', 'alpha_num', 'unique:branches,code'],
            'business_type_id' => ['nullable', 'integer', 'exists:business_types,id'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['branch_name' => 'branch name', 'code' => 'branch code'];
    }
}
