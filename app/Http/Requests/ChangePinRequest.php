<?php

namespace App\Http\Requests;

class ChangePinRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_pin' => ['required', 'string', 'digits_between:4,6'],
            'pin' => ['required', 'string', 'digits_between:4,6', 'confirmed'],
        ];
    }
}
