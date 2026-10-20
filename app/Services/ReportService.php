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
            // Hostels report one collected total; categories stay blank.
            $total = (float) ($data['total_rev'] ?? 0);
            $rooms = 0.0;
            $bar = 0.0;
            $food = 0.0;
            $sauna = 0.0;
            $other = 0.0;
            $bedsOccupied = null;
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
            $bedsOccupied = null;
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
            'beds_occupied' => $bedsOccupied,
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

        $this->whatsAppNotifier->notifyReportSubmitted($report->load('branch'));

        return $report;
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

    public function isLockedForDate(string $date): bool
    {
        $deadline = (string) config('app.report_deadline', '21:00');

        $today = now()->toDateString();

        if ($date < $today) {
            return true;
        }

        if ($date > $today) {
            return false;
        }

        return now()->greaterThanOrEqualTo(
            Carbon::parse($today . ' ' . $deadline)
        );
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
