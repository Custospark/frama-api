<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use App\Enums\CustomFieldScope;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Services\Contracts\ReportServiceInterface;
use App\Services\WhatsAppNotifier;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class ReportService implements ReportServiceInterface
{
    public function __construct(
        protected ReportRepositoryInterface $reportRepository,
        protected WhatsAppNotifier $whatsAppNotifier,
        protected DynamicFieldValidator $dynamicFieldValidator,
    ) {}

    public function paginate(User $user, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->reportRepository->paginate($this->scopeFilters($user, $filters), $perPage);
    }

    public function filters(User $user, array $filters): Collection
    {
        return $this->reportRepository->filters($this->scopeFilters($user, $filters));
    }

    public function getById(User $user, int $id): ?Report
    {
        $report = $this->reportRepository->find($id);

        if (! $report) {
            return null;
        }

        if (! $user->canViewAllBranches() && $report->branch_id !== $user->branch_id) {
            return null;
        }

        return $report;
    }

    public function submit(User $user, array $data, array $receiptFiles, array $expenseFiles): Report
    {
        if (! $user->branch_id) {
            throw new RuntimeException('Your account is not linked to a branch.');
        }

        $date = Carbon::parse($data['report_date'])->toDateString();

        if ($date > now()->toDateString()) {
            throw new RuntimeException('You cannot submit a report for a future date.');
        }

        if ($this->reportRepository->findForBranchOnDate($user->branch_id, $date)) {
            throw new RuntimeException('A report has already been submitted for this date.');
        }

        if (empty($receiptFiles)) {
            throw new RuntimeException('At least one receipt photo is required.');
        }

        if (empty($expenseFiles)) {
            throw new RuntimeException('At least one expense receipt is required.');
        }

        $isHostel = $user->branch?->businessType?->slug === 'hostel';

        if ($isHostel) {
            // Hostels report rooms occupied, the semester target and collections.
            $total = (float) ($data['total_rev'] ?? 0);
            $rooms = 0.0;
            $bar = 0.0;
            $food = 0.0;
            $sauna = 0.0;
            $other = 0.0;
            $roomsOccupied = (int) ($data['rooms_occupied'] ?? 0);
            $amountExpected = isset($data['amount_expected_semester'])
                ? (float) $data['amount_expected_semester']
                : null;
            // Semester expectations change rarely; carry the last submitted
            // value forward. Collections are cumulative and computed server-side.
            if ($amountExpected === null) {
                $previous = $this->previousHostelReport($user->branch_id, $date);
                $amountExpected = $previous?->amount_expected_semester !== null
                    ? (float) $previous->amount_expected_semester
                    : null;
            }

            $collectedSoFar = $this->nextCollectedSoFar($user->branch_id, $date, $total);
        } else {
            $rooms = (float) ($data['rooms_rev'] ?? 0);
            $bar = (float) ($data['bar_rev'] ?? 0);
            $food = (float) ($data['food_rev'] ?? 0);
            $sauna = (float) ($data['sauna_rev'] ?? 0);
            $other = (float) ($data['other_rev'] ?? 0);
            $total = Report::computeTotal([
                'rooms_rev' => $rooms,
                'bar_rev' => $bar,
                'food_rev' => $food,
                'sauna_rev' => $sauna,
                'other_rev' => $other,
            ]);
            $roomsOccupied = null;
            $amountExpected = null;
            $collectedSoFar = null;
        }

        $customValues = $this->extractCustomValues($user->branch_id, [
            ...$data,
            'rooms_rev' => $rooms,
            'bar_rev' => $bar,
            'food_rev' => $food,
            'sauna_rev' => $sauna,
            'other_rev' => $other,
            'total_rev' => $total,
        ]);

        $businessTypeIds = collect($data['business_type_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $report = $this->reportRepository->create([
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'report_date' => $date,
            'rooms_rev' => $rooms,
            'bar_rev' => $bar,
            'food_rev' => $food,
            'sauna_rev' => $sauna,
            'other_rev' => $other,
            'rooms_occupied' => $roomsOccupied,
            'amount_expected_semester' => $amountExpected,
            'collected_so_far' => $collectedSoFar,
            'total_rev' => $total,
            'expenses' => $data['expenses'] ?? 0,
            'cash_handed_over' => $data['cash_handed_over'] ?? 0,
            'comment' => $data['comment'] ?? null,
            'business_type_ids' => $businessTypeIds ?: null,
            'manager_name' => $user->name,
            'submitted_at' => now(),
            'custom_values' => $customValues ?: null,
        ]);

        $this->storeAttachments($report, $receiptFiles, 'receipt');
        $this->storeAttachments($report, $expenseFiles, 'expense');

        if ($isHostel) {
            $this->resequenceFollowingCollections($report);
        }

        $this->whatsAppNotifier->notifyReportSubmitted($report->load('branch'));

        return $report;
    }

    public function update(User $user, int $id, array $data, array $receiptFiles, array $expenseFiles): Report
    {
        $report = $this->getById($user, $id);

        if (! $report) {
            throw new RuntimeException('Report not found.');
        }

        if (! $report->allow_edit) {
            throw new RuntimeException('Editing has not been enabled for this report.');
        }

        if ($report->branch_id !== $user->branch_id) {
            throw new RuntimeException('You can only edit reports for your own branch.');
        }

        $date = $report->report_date->toDateString();
        $isHostel = $report->branch?->businessType?->slug === 'hostel';

        if ($isHostel) {
            $total = (float) ($data['total_rev'] ?? 0);
            $rooms = 0.0;
            $bar = 0.0;
            $food = 0.0;
            $sauna = 0.0;
            $other = 0.0;
            $roomsOccupied = (int) ($data['rooms_occupied'] ?? 0);
            $amountExpected = array_key_exists('amount_expected_semester', $data)
                ? (float) $data['amount_expected_semester']
                : ($report->amount_expected_semester !== null ? (float) $report->amount_expected_semester : null);
            $baseline = max(0, (float) $report->collected_so_far - (float) $report->total_rev);
            $collectedSoFar = round($baseline + $total, 2);
        } else {
            $rooms = (float) ($data['rooms_rev'] ?? 0);
            $bar = (float) ($data['bar_rev'] ?? 0);
            $food = (float) ($data['food_rev'] ?? 0);
            $sauna = (float) ($data['sauna_rev'] ?? 0);
            $other = (float) ($data['other_rev'] ?? 0);
            $total = Report::computeTotal([
                'rooms_rev' => $rooms,
                'bar_rev' => $bar,
                'food_rev' => $food,
                'sauna_rev' => $sauna,
                'other_rev' => $other,
            ]);
            $roomsOccupied = null;
            $amountExpected = null;
            $collectedSoFar = null;
        }

        $extracted = $this->extractCustomValues($report->branch_id, [
            ...$data,
            'rooms_rev' => $rooms,
            'bar_rev' => $bar,
            'food_rev' => $food,
            'sauna_rev' => $sauna,
            'other_rev' => $other,
            'total_rev' => $total,
        ]);
        $customValues = array_merge($report->custom_values ?? [], $extracted);

        $businessTypeIds = collect($data['business_type_ids'] ?? $report->business_type_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $report = $this->reportRepository->update($report, [
            'rooms_rev' => $rooms,
            'bar_rev' => $bar,
            'food_rev' => $food,
            'sauna_rev' => $sauna,
            'other_rev' => $other,
            'rooms_occupied' => $roomsOccupied,
            'amount_expected_semester' => $amountExpected,
            'collected_so_far' => $collectedSoFar,
            'total_rev' => $total,
            'expenses' => $data['expenses'] ?? 0,
            'cash_handed_over' => $data['cash_handed_over'] ?? 0,
            'comment' => $data['comment'] ?? null,
            'business_type_ids' => $businessTypeIds ?: null,
            'custom_values' => $customValues ?: null,
            'allow_edit' => false,
        ]);

        $this->storeAttachments($report, $receiptFiles, 'receipt');
        $this->storeAttachments($report, $expenseFiles, 'expense');

        if ($isHostel) {
            $this->resequenceFollowingCollections($report);
        }

        return $report->fresh();
    }

    public function setEditPermission(User $user, int $id, bool $allow): ?Report
    {
        $report = $this->reportRepository->find($id);

        if (! $report) {
            return null;
        }

        return $this->reportRepository->update($report, ['allow_edit' => $allow]);
    }

    /**
     * Keep later hostel reports consistent when an earlier collection total
     * is corrected: each following report adds its own daily total.
     */
    protected function resequenceFollowingCollections(Report $report): void
    {
        $running = (float) $report->collected_so_far;
        $following = Report::query()
            ->where('branch_id', $report->branch_id)
            ->whereDate('report_date', '>', $report->report_date->toDateString())
            ->orderBy('report_date')
            ->orderBy('id')
            ->get();

        foreach ($following as $next) {
            $running = round($running + (float) $next->total_rev, 2);

            if ((float) $next->collected_so_far !== $running) {
                $next->updateQuietly(['collected_so_far' => $running]);
            }
        }
    }

    /**
     * Latest report before the given date, used to carry semester figures.
     */
    protected function previousHostelReport(int $branchId, string $date, ?int $excludeId = null): ?Report
    {
        return Report::query()
            ->where('branch_id', $branchId)
            ->whereDate('report_date', '<', $date)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->latest('report_date')
            ->latest('id')
            ->first();
    }

    /**
     * Collections are cumulative: the previous cumulative total plus today's
     * collection. Clients cannot override this figure.
     */
    protected function nextCollectedSoFar(int $branchId, string $date, float $todayTotal): float
    {
        $previous = $this->previousHostelReport($branchId, $date);

        return round((float) ($previous?->collected_so_far ?? 0) + $todayTotal, 2);
    }

    /**
     * Persist every uploaded file as a report attachment in the given group
     * ('receipt' or 'expense'). Zip archives are flagged so the UI can present
     * them distinctly.
     *
     * @param  array<int, UploadedFile>  $files
     */
    protected function storeAttachments(Report $report, array $files, string $group = 'receipt'): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('reports/attachments', 'public');
            $original = $file->getClientOriginalName();

            $report->attachments()->create([
                'group' => $group,
                'original_name' => $original,
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'is_archive' => in_array(strtolower($file->getClientOriginalExtension()), ['zip', 'gz', 'tar', 'rar', '7z'], true),
            ]);
        }
    }

    protected function scopeFilters(User $user, array $filters): array
    {
        if (! $user->canViewAllBranches()) {
            $filters['branch_id'] = $user->branch_id;
        }

        return $filters;
    }

    /**
     * Merge every report-scoped custom field definition for the branch (across
     * all business types) and persist the submitted values. Calculated fields
     * can reference the fixed revenue streams plus other custom values.
     */
    protected function extractCustomValues(?int $branchId, array $data): array
    {
        $definitions = $this->dynamicFieldValidator->definitionsForAllTypes(CustomFieldScope::Report, $branchId);

        $baseVariables = [
            'rooms_rev' => (float) ($data['rooms_rev'] ?? 0),
            'bar_rev' => (float) ($data['bar_rev'] ?? 0),
            'food_rev' => (float) ($data['food_rev'] ?? 0),
            'sauna_rev' => (float) ($data['sauna_rev'] ?? 0),
            'other_rev' => (float) ($data['other_rev'] ?? 0),
            'expenses' => (float) ($data['expenses'] ?? 0),
            'cash_handed_over' => (float) ($data['cash_handed_over'] ?? 0),
            'total_rev' => (float) ($data['total_rev'] ?? 0),
        ];

        try {
            return $this->dynamicFieldValidator->extractValues(
                $definitions,
                $data['custom_values'] ?? [],
                'reports/custom',
                $baseVariables,
            );
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Division by zero') || str_contains($message, 'Modulo by zero')) {
                $message .= ' Guard the formula with if(denominator>0, numerator/denominator, 0).';
            }

            throw ValidationException::withMessages(['custom_values' => $message]);
        }
    }
}
