<?php

namespace App\Repositories\Contracts;

use App\Models\Report;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ReportRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function filters(array $filters): Collection;

    public function find(int $id): ?Report;

    public function findForBranchOnDate(int $branchId, string $date): ?Report;

    public function create(array $data): Report;

    public function update(Report $report, array $data): Report;
}
