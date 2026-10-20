<?php

namespace App\Http\Requests;

class ChangePinByCodeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'access_code' => ['required', 'string', 'max:32'],
            'current_pin' => ['required', 'string', 'digits_between:4,6'],
            'pin' => ['required', 'string', 'digits_between:4,6', 'confirmed', 'different:current_pin'],
        ];
    }

    public function attributes(): array
    {
        return [
            'access_code' => 'access code',
            'current_pin' => 'current PIN',
            'pin' => 'new PIN',
        ];
    }
}
