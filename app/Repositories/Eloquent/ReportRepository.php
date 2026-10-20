<?php

namespace App\Repositories\Eloquent;

use App\Models\Report;
use App\Repositories\Contracts\ReportRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReportRepository implements ReportRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query($filters)
            ->with(['branch', 'user', 'attachments'])
            ->latest('report_date')
            ->latest('id')
            ->paginate($perPage);
    }

    public function filters(array $filters): Collection
    {
        return $this->query($filters)
            ->with(['branch', 'user', 'attachments'])
            ->latest('report_date')
            ->latest('id')
            ->get();
    }

    public function find(int $id): ?Report
    {
        return Report::with(['branch', 'user', 'attachments'])->find($id);
    }

    public function findForBranchOnDate(int $branchId, string $date): ?Report
    {
        return Report::where('branch_id', $branchId)
            ->whereDate('report_date', $date)
            ->first();
    }

    public function create(array $data): Report
    {
        return Report::create($data);
    }

    public function update(Report $report, array $data): Report
    {
        $report->update($data);

        return $report->fresh();
    }

    protected function query(array $filters): Builder
    {
        return Report::query()
            ->betweenDates($filters['from'] ?? null, $filters['to'] ?? null)
            ->when(! empty($filters['branch_id']), fn (Builder $q) => $q->forBranch((int) $filters['branch_id']));
    }
}
