<?php

namespace App\Services\Contracts;

interface BranchShareServiceInterface
{
    /**
     * Public, read-only summary for a single branch identified by its URL slug.
     * Exposes aggregated revenue figures only - never receipt images or other branches.
     *
     * @return array{
     *     branch: array{id: int, branch_name: string, slug: string|null, manager_name: string|null},
     *     today: array<string, mixed>|null,
     *     recent: array<int, array<string, mixed>>,
     *     trend: array<int, array{date: string, total: float}>,
     *     month_total: float,
     *     submitted_this_month: int,
     *     generated_at: string
     * }|null
     */
    public function summary(string $slug, int $trendDays = 14): ?array;
}
