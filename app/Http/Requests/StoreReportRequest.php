<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldScope;
use App\Services\DynamicFieldValidator;

class StoreReportRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'expenses' => ['required', 'numeric', 'min:0'],
            'cash_handed_over' => ['required', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'receipt_photos' => ['required', 'array', 'min:1', 'max:50'],
            'receipt_photos.*' => ['required', 'file', 'max:51200'],
            'expense_receipts' => ['required', 'array', 'min:1', 'max:50'],
            'expense_receipts.*' => ['required', 'file', 'max:51200'],
        ];

        if ($this->isHostelBranch()) {
            $rules['rooms_occupied'] = ['required', 'integer', 'min:0'];
            $rules['amount_expected_semester'] = ['nullable', 'numeric', 'min:0'];
            $rules['collected_so_far'] = ['nullable', 'numeric', 'min:0'];
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

    /** Hostel branches collect a single daily total; motel branches the category breakdown. */
    private function isHostelBranch(): bool
    {
        return $this->user()?->branch?->businessType?->slug === 'hostel';
    }

    /**
     * Report-scoped custom field rules for the submitting manager's branch,
     * across every business type (fields are grouped by type on the form).
     */
    private function customFieldRules(): array
    {
        $user = $this->user();
        $validator = app(DynamicFieldValidator::class);

        $definitions = $validator->definitionsForAllTypes(CustomFieldScope::Report, $user?->branch_id);

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
            'collected_so_far' => 'money collected so far',
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
