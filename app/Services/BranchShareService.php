<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Report;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Services\Contracts\BranchShareServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BranchShareService implements BranchShareServiceInterface
{
    public function __construct(
        protected BranchRepositoryInterface $branchRepository,
    ) {}

    public function summary(string $slug, int $trendDays = 14): ?array
    {
        $branch = $this->branchRepository->findBySlug($slug);

        if (! $branch) {
            return null;
        }

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $trendFrom = now()->subDays(max($trendDays - 1, 0))->toDateString();

        $todayReport = Report::query()
            ->forBranch($branch->id)
            ->whereDate('report_date', $today)
            ->first();

        $recent = Report::query()
            ->forBranch($branch->id)
            ->orderByDesc('report_date')
            ->limit(10)
            ->get()
            ->map(fn (Report $report) => $this->presentReport($report))
            ->all();

        $trend = Report::query()
            ->forBranch($branch->id)
            ->betweenDates($trendFrom, $today)
            ->select('report_date', DB::raw('SUM(total_rev) as total'))
            ->groupBy('report_date')
            ->orderBy('report_date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->report_date)->toDateString(),
                'total' => (float) $row->total,
            ])
            ->all();

        $monthAggregate = Report::query()
            ->forBranch($branch->id)
            ->betweenDates($monthStart, $today)
            ->selectRaw('COALESCE(SUM(total_rev), 0) as total, COUNT(*) as reports')
            ->first();

        return [
            'branch' => [
                'id' => $branch->id,
                'branch_name' => $branch->branch_name,
                'slug' => $branch->slug,
                'manager_name' => $branch->manager_name,
            ],
            'today' => $todayReport ? $this->presentReport($todayReport) : null,
            'recent' => $recent,
            'trend' => $trend,
            'month_total' => round((float) ($monthAggregate->total ?? 0), 2),
            'submitted_this_month' => (int) ($monthAggregate->reports ?? 0),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReport(Report $report): array
    {
        return [
            'report_date' => $report->report_date?->toDateString(),
            'rooms_rev' => (float) $report->rooms_rev,
            'bar_rev' => (float) $report->bar_rev,
            'food_rev' => (float) $report->food_rev,
            'sauna_rev' => (float) $report->sauna_rev,
            'total_rev' => (float) $report->total_rev,
            'manager_name' => $report->manager_name,
            'submitted_at' => $report->submitted_at?->toIso8601String(),
        ];
    }
}
