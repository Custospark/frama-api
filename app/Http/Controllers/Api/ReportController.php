<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ReportFilterRequest;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Services\Contracts\ReportServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

class ReportController extends BaseApiController
{
    public function __construct(
        protected ReportServiceInterface $reportService,
    ) {}

    public function index(ReportFilterRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 20);
        $paginator = $this->reportService->paginate($request->user(), $request->validated(), $perPage);

        return ReportResource::collection($paginator)->response();
    }

    public function show(Request $request, int $report): JsonResponse
    {
        $found = $this->reportService->getById($request->user(), $report);

        if (! $found) {
            return ApiResponse::error('Report not found.', 404);
        }

        return (new ReportResource($found))->response();
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        if ($this->reportService->isLockedForDate($request->validated('report_date'))) {
            return ApiResponse::error('Reports for this date are locked (past the deadline).', 422);
        }

        try {
            $report = $this->reportService->submit(
                $request->user(),
                $request->validated(),
                $request->file('attachments') ?? [],
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return (new ReportResource($report->load(['branch', 'attachments'])))->response()->setStatusCode(201);
    }

    public function downloadAttachment(Request $request, int $report, int $attachment): StreamedResponse|JsonResponse
    {
        $found = $this->reportService->getById($request->user(), $report);

        if (! $found) {
            return ApiResponse::error('Report not found.', 404);
        }

        $file = $found->attachments()->find($attachment);

        if (! $file) {
            return ApiResponse::error('Attachment not found.', 404);
        }

        return Storage::disk('public')->download($file->path, $file->original_name);
    }

    public function downloadAll(Request $request, int $report): StreamedResponse|JsonResponse
    {
        $found = $this->reportService->getById($request->user(), $report);

        if (! $found) {
            return ApiResponse::error('Report not found.', 404);
        }

        $attachments = $found->attachments()->get();

        if ($attachments->isEmpty()) {
            return ApiResponse::error('This report has no attachments.', 404);
        }

        $zipName = sprintf(
            'report-%s-%s.zip',
            $found->report_date?->toDateString() ?? $found->id,
            \Illuminate\Support\Str::slug($found->branch?->branch_name ?? 'branch'),
        );

        $disk = Storage::disk('public');

        return response()->streamDownload(function () use ($attachments, $disk) {
            $zip = new ZipStream\ZipStream($zipName);

            $used = [];
            foreach ($attachments as $file) {
                if (! $disk->exists($file->path)) {
                    continue;
                }

                $name = $this->uniqueName($used, $file->original_name);
                $zip->addFileFromPath($name, $disk->path($file->path));
            }

            $zip->finish();
        }, $zipName, ['Content-Type' => 'application/zip']);
    }

    /**
     * @param  array<string, bool>  $used
     */
    private function uniqueName(array &$used, string $name): string
    {
        if (! isset($used[$name])) {
            $used[$name] = true;

            return $name;
        }

        $info = pathinfo($name);
        $base = $info['filename'] ?? 'file';
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        $i = 2;

        while (isset($used[$base . '-' . $i . $ext])) {
            $i++;
        }

        $unique = $base . '-' . $i . $ext;
        $used[$unique] = true;

        return $unique;
    }

    public function export(ReportFilterRequest $request): StreamedResponse
    {
        $reports = $this->reportService->filters($request->user(), $request->validated());

        $customColumns = $this->customColumnKeys($reports);

        return response()->streamDownload(function () use ($reports, $customColumns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(
                ['Date', 'Branch', 'Rooms', 'Bar', 'Food', 'Sauna', 'Total', 'Expenses', 'Cash Handed Over', 'Comment', 'Manager', 'Status'],
                array_map(fn ($col) => $col['label'], $customColumns),
            ));

            foreach ($reports as $report) {
                $values = $report->custom_values ?? [];
                $customCells = array_map(fn ($col) => $values[$col['key']] ?? '', $customColumns);

                fputcsv($out, array_merge([
                    $report->report_date?->toDateString(),
                    $report->branch?->branch_name,
                    $report->rooms_rev,
                    $report->bar_rev,
                    $report->food_rev,
                    $report->sauna_rev,
                    $report->total_rev,
                    $report->expenses,
                    $report->cash_handed_over,
                    $report->comment,
                    $report->manager_name,
                    $report->submitted_at ? 'Submitted' : 'Pending',
                ], $customCells));
            }

            fclose($out);
        }, 'frama-reports-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Distinct report-scope custom field keys present in the result set, in a
     * stable order, used to build the CSV header.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Report>  $reports
     * @return array<int, array{key: string, label: string}>
     */
    private function customColumnKeys($reports): array
    {
        $keys = [];
        foreach ($reports as $report) {
            foreach (array_keys($report->custom_values ?? []) as $key) {
                $keys[$key] = true;
            }
        }

        if ($keys === []) {
            return [];
        }

        $branchIds = $reports->pluck('branch_id')->unique()->all();
        $validator = app(\App\Services\DynamicFieldValidator::class);

        $labels = [];
        foreach ($branchIds as $branchId) {
            $definitions = $validator->definitionsForAllTypes(\App\Enums\CustomFieldScope::Report, $branchId);
            foreach ($definitions as $field) {
                $labels[$field->key] = $field->label;
            }
        }

        return collect(array_keys($keys))
            ->map(fn ($key) => ['key' => $key, 'label' => $labels[$key] ?? \Illuminate\Support\Str::headline($key)])
            ->values()
            ->all();
    }
}
