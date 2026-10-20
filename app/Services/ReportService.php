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

    public function submit(User $user, array $data, array $attachments): Report
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

        if (empty($attachments)) {
            throw new RuntimeException('At least one attachment is required.');
        }

        $total = Report::computeTotal($data);

        $customValues = $this->extractCustomValues($user->branch_id, $data);

        $businessTypeIds = collect($data['business_type_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $report = $this->reportRepository->create([
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'report_date' => $date,
            'rooms_rev' => $data['rooms_rev'],
            'bar_rev' => $data['bar_rev'],
            'food_rev' => $data['food_rev'],
            'sauna_rev' => $data['sauna_rev'],
            'total_rev' => $total,
            'expenses' => $data['expenses'] ?? 0,
            'cash_handed_over' => $data['cash_handed_over'] ?? 0,
            'comment' => $data['comment'] ?? null,
            'business_type_ids' => $businessTypeIds ?: null,
            'manager_name' => $user->name,
            'submitted_at' => now(),
            'custom_values' => $customValues ?: null,
        ]);

        $this->storeAttachments($report, $attachments);

        $this->whatsAppNotifier->notifyReportSubmitted($report->load('branch'));

        return $report;
    }

    /**
     * Persist every uploaded file as a report attachment. Zip archives are
     * flagged so the UI can present them distinctly.
     *
     * @param  array<int, UploadedFile>  $attachments
     */
    protected function storeAttachments(Report $report, array $attachments): void
    {
        foreach ($attachments as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('reports/attachments', 'public');
            $original = $file->getClientOriginalName();

            $report->attachments()->create([
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
            'expenses' => (float) ($data['expenses'] ?? 0),
            'cash_handed_over' => (float) ($data['cash_handed_over'] ?? 0),
            'total_rev' => (float) Report::computeTotal($data),
        ];

        return $this->dynamicFieldValidator->extractValues(
            $definitions,
            $data['custom_values'] ?? [],
            'reports/custom',
            $baseVariables,
        );
    }
}
