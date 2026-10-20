<?php

namespace App\Http\Requests;

class LoginRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'access_code' => ['required', 'string', 'max:32'],
            'pin' => ['required', 'string', 'digits_between:4,6'],
        ];
    }

    public function attributes(): array
    {
        return [
            'access_code' => 'access code',
        ];
    }
}
