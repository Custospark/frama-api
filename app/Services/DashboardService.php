<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Report;
use App\Services\Contracts\DashboardServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService implements DashboardServiceInterface
{
    public function summary(?string $date = null): array
    {
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        $branches = Branch::where('is_active', true)->orderBy('branch_name')->get();

        $reports = Report::with(['user', 'attachments'])
            ->whereDate('report_date', $date)
            ->get()
            ->keyBy('branch_id');

        $rows = [];
        $grandTotal = 0.0;
        $expensesTotal = 0.0;
        $cashHandedTotal = 0.0;
        $submitted = 0;
        $customGrandTotals = [];

        $reportDefinitions = $this->reportCustomDefinitions($branches);

        foreach ($branches as $branch) {
            $report = $reports->get($branch->id);

            $customValues = $report?->custom_values ?? [];

            if ($report) {
                $submitted++;
                $grandTotal += (float) $report->total_rev;
                $expensesTotal += (float) $report->expenses;
                $cashHandedTotal += (float) $report->cash_handed_over;

                foreach ($customValues as $key => $value) {
                    if (is_numeric($value) && $this->isNumericField($key, $reportDefinitions)) {
                        $customGrandTotals[$key] = ($customGrandTotals[$key] ?? 0) + (float) $value;
                    }
                }
            }

            $rows[] = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->branch_name,
                'branch_code' => $branch->code,
                'date' => $date,
                'rooms_rev' => $report ? (float) $report->rooms_rev : 0.0,
                'bar_rev' => $report ? (float) $report->bar_rev : 0.0,
                'food_rev' => $report ? (float) $report->food_rev : 0.0,
                'sauna_rev' => $report ? (float) $report->sauna_rev : 0.0,
                'total_rev' => $report ? (float) $report->total_rev : 0.0,
                'expenses' => $report ? (float) $report->expenses : 0.0,
                'net_rev' => $report ? (float) ($report->total_rev - $report->expenses) : 0.0,
                'cash_handed_over' => $report ? (float) $report->cash_handed_over : 0.0,
                'comment' => $report?->comment,
                'manager_name' => $report?->manager_name ?? $branch->manager_name,
                'attachments_count' => $report ? $report->attachments->count() : 0,
                'first_attachment_url' => $report && $report->attachments->isNotEmpty()
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($report->attachments->first()->path)
                    : null,
                'first_attachment_is_image' => $report && $report->attachments->isNotEmpty()
                    ? str_starts_with((string) $report->attachments->first()->mime_type, 'image/')
                    : false,
                'report_id' => $report?->id,
                'attachments' => $report
                    ? \App\Http\Resources\ReportAttachmentResource::collection($report->attachments)->resolve()
                    : [],
                'status' => $report ? 'Submitted' : 'Pending',
                'submitted_at' => $report?->submitted_at?->toIso8601String(),
                'custom_values' => $this->presentReportCustomValues($customValues),
            ];
        }

        return [
            'date' => $date,
            'rows' => $rows,
            'grand_total' => round($grandTotal, 2),
            'expenses_total' => round($expensesTotal, 2),
            'net_total' => round($grandTotal - $expensesTotal, 2),
            'cash_handed_total' => round($cashHandedTotal, 2),
            'submitted_count' => $submitted,
            'pending_count' => count($rows) - $submitted,
            'custom_fields' => $this->reportCustomFieldsMeta($reportDefinitions),
            'custom_grand_totals' => $customGrandTotals,
        ];
    }

    public function trend(?string $from = null, ?string $to = null): array
    {
        $from = $from ? Carbon::parse($from)->toDateString() : now()->subDays(13)->toDateString();
        $to = $to ? Carbon::parse($to)->toDateString() : now()->toDateString();

        $rows = Report::query()
            ->betweenDates($from, $to)
            ->select('report_date', DB::raw('SUM(total_rev) as total'))
            ->groupBy('report_date')
            ->orderBy('report_date')
            ->get();

        return $rows->map(fn ($row) => [
            'date' => Carbon::parse($row->report_date)->toDateString(),
            'total' => (float) $row->total,
        ])->all();
    }

    /**
     * Report-scoped custom field definitions across the branches shown.
     *
     * @param  \Illuminate\Support\Collection<int, Branch>  $branches
     * @return \Illuminate\Support\Collection<int, \App\Models\CustomField>
     */
    private function reportCustomDefinitions($branches)
    {
        $branchIds = $branches->pluck('id')->all();
        $validator = app(\App\Services\DynamicFieldValidator::class);

        $definitions = collect();
        foreach ($branchIds as $branchId) {
            $definitions = $definitions->merge(
                $validator->definitionsForAllTypes(\App\Enums\CustomFieldScope::Report, $branchId)
            );
        }

        return $definitions->unique('key')->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\CustomField>  $definitions
     */
    private function isNumericField(string $key, $definitions): bool
    {
        $field = $definitions->firstWhere('key', $key);

        return $field && $field->type->isNumeric();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\CustomField>  $definitions
     * @return array<int, array<string, mixed>>
     */
    private function reportCustomFieldsMeta($definitions): array
    {
        return $definitions->map(fn ($field) => [
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type->value,
            'business_type_id' => $field->business_type_id,
            'business_type' => $field->businessType?->name,
            'aggregate' => $field->type === \App\Enums\CustomFieldType::Number,
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function presentReportCustomValues(array $values): array
    {
        return collect($values)->map(function ($value) {
            if (is_string($value) && str_starts_with($value, 'reports/custom/')) {
                return \Illuminate\Support\Facades\Storage::disk('public')->url($value);
            }

            return $value;
        })->all();
    }
}
