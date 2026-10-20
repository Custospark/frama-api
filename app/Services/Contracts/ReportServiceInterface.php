<?php

namespace App\Services\Contracts;

use App\Models\Report;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ReportServiceInterface
{
    public function paginate(User $user, array $filters, int $perPage = 20): LengthAwarePaginator;

    public function filters(User $user, array $filters): Collection;

    public function getById(User $user, int $id): ?Report;

    public function submit(User $user, array $data, array $receiptFiles, array $expenseFiles): Report;
}
