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
            'rooms_rev' => ['required', 'numeric', 'min:0'],
            'bar_rev' => ['required', 'numeric', 'min:0'],
            'food_rev' => ['required', 'numeric', 'min:0'],
            'sauna_rev' => ['required', 'numeric', 'min:0'],
            'expenses' => ['required', 'numeric', 'min:0'],
            'cash_handed_over' => ['required', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'business_type_ids' => ['nullable', 'array'],
            'business_type_ids.*' => ['integer', 'exists:business_types,id'],
            'attachments' => ['required', 'array', 'min:1', 'max:50'],
            'attachments.*' => ['required', 'file', 'max:51200'],
        ];

        return array_merge($rules, $this->customFieldRules());
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
            'expenses' => 'expenses',
            'cash_handed_over' => 'cash handed over',
            'comment' => 'comment',
            'attachments' => 'attachments',
            'attachments.*' => 'attachment',
        ];
    }
}
