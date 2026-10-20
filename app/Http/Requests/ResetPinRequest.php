<?php

namespace App\Http\Requests;

class ResetPinRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'digits_between:4,6'],
        ];
    }
}
