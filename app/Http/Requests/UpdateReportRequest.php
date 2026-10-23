<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldScope;
use App\Services\DynamicFieldValidator;

class UpdateReportRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'expenses' => ['required', 'numeric', 'min:0'],
            'cash_handed_over' => ['required', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'receipt_photos' => ['nullable', 'array', 'max:50'],
            'receipt_photos.*' => ['required', 'file', 'max:51200'],
            'expense_receipts' => ['nullable', 'array', 'max:50'],
            'expense_receipts.*' => ['required', 'file', 'max:51200'],
            'business_type_ids' => ['nullable', 'array'],
            'business_type_ids.*' => ['integer', 'exists:business_types,id'],
        ];

        if ($this->isHostelBranch()) {
            $rules['rooms_occupied'] = ['required', 'integer', 'min:0'];
            $rules['amount_expected_semester'] = ['nullable', 'numeric', 'min:0'];
            $rules['total_rev'] = ['required', 'numeric', 'min:0'];
        } else {
            $rules['rooms_rev'] = ['required', 'numeric', 'min:0'];
            $rules['bar_rev'] = ['nullable', 'numeric', 'min:0'];
            $rules['food_rev'] = ['required', 'numeric', 'min:0'];
            $rules['sauna_rev'] = ['required', 'numeric', 'min:0'];
            $rules['other_rev'] = ['required', 'numeric', 'min:0'];
        }

        return array_merge($rules, $this->customFieldRules());
    }

    private function isHostelBranch(): bool
    {
        return $this->user()?->branch?->businessType?->slug === 'hostel';
    }

    private function customFieldRules(): array
    {
        $validator = app(DynamicFieldValidator::class);

        $definitions = $validator->definitionsForAllTypes(
            CustomFieldScope::Report,
            $this->user()?->branch_id,
        );

        return $validator->rules($definitions);
    }

    public function attributes(): array
    {
        return [
            'rooms_rev' => 'rooms revenue',
            'bar_rev' => 'bar revenue',
            'food_rev' => 'food revenue',
            'sauna_rev' => 'sauna revenue',
            'other_rev' => 'other revenue',
            'rooms_occupied' => 'total rooms occupied',
            'amount_expected_semester' => 'amount expected this semester',
            'total_rev' => 'total collected today',
            'expenses' => 'expenses',
            'cash_handed_over' => 'cash handed over',
            'comment' => 'comment',
            'receipt_photos' => 'receipt photos',
            'receipt_photos.*' => 'receipt photo',
            'expense_receipts' => 'expense receipts',
            'expense_receipts.*' => 'expense receipt',
        ];
    }
}
