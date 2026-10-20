<?php

namespace App\Services\Contracts;

interface DashboardServiceInterface
{
    /**
     * One entry per active branch for the given date, merged with the report
     * submitted for that date (or a Pending placeholder), plus a grand total.
     *
     * @return array{date: string, rows: array<int, array<string, mixed>>, grand_total: float, submitted_count: int, pending_count: int}
     */
    public function summary(?string $date = null): array;

    /**
     * Per-day totals for the trend chart.
     *
     * @return array<int, array{date: string, total: float}>
     */
    public function trend(?string $from = null, ?string $to = null): array;
}
