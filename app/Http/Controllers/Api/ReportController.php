<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReportsExport;
use App\Http\Requests\ReportFilterRequest;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Services\Contracts\ReportServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
        try {
            $report = $this->reportService->submit(
                $request->user(),
                $request->validated(),
                $request->file('receipt_photos') ?? [],
                $request->file('expense_receipts') ?? [],
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

    public function export(ReportFilterRequest $request): BinaryFileResponse
    {
        $reports = $this->reportService->filters($request->user(), $request->validated());

        return Excel::download(
            new ReportsExport($reports, $this->customColumnKeys($reports)),
            'frama-reports-' . now()->format('Ymd-His') . '.xlsx',
        );
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
