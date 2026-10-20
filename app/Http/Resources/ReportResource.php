<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch->branch_name),
            'branch_code' => $this->whenLoaded('branch', fn () => $this->branch->code),
            'report_date' => $this->report_date?->toDateString(),
            'rooms_rev' => (float) $this->rooms_rev,
            'bar_rev' => (float) $this->bar_rev,
            'food_rev' => (float) $this->food_rev,
            'sauna_rev' => (float) $this->sauna_rev,
            'other_rev' => (float) $this->other_rev,
            'beds_occupied' => $this->beds_occupied,
            'total_rev' => (float) $this->total_rev,
            'expenses' => (float) $this->expenses,
            'cash_handed_over' => (float) $this->cash_handed_over,
            'comment' => $this->comment,
            'business_type_ids' => $this->business_type_ids ?? [],
            'business_types' => $this->presentBusinessTypes(),
            'attachments' => ReportAttachmentResource::collection($this->whenLoaded('attachments')),
            'attachments_count' => $this->whenLoaded('attachments', fn () => $this->attachments->count()),
            'receipt_photos' => $this->whenLoaded('attachments', fn () => ReportAttachmentResource::collection($this->attachments->where('group', 'receipt'))),
            'expense_receipts' => $this->whenLoaded('attachments', fn () => ReportAttachmentResource::collection($this->attachments->where('group', 'expense'))),
            'downloads_all_url' => route('reports.attachments.downloadAll', ['report' => $this->id]),
            'manager_name' => $this->manager_name,
            'status' => $this->submitted_at ? 'Submitted' : 'Pending',
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'custom_values' => $this->presentCustomValues(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve the selected business types to id/name/slug triples for display.
     *
     * @return array<int, array{id: int, name: string, slug: string|null}>
     */
    private function presentBusinessTypes(): array
    {
        $ids = $this->business_type_ids ?? [];

        if (empty($ids)) {
            return [];
        }

        return \App\Models\BusinessType::query()
            ->whereIn('id', $ids)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCustomValues(): array
    {
        $values = $this->custom_values ?? [];

        return collect($values)->map(function ($value) {
            if (is_string($value) && str_starts_with($value, 'reports/custom/')) {
                return Storage::disk('public')->url($value);
            }

            return $value;
        })->all();
    }
}
