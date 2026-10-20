<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReportAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group ?? 'receipt',
            'original_name' => $this->original_name,
            'url' => Storage::disk('public')->url($this->path),
            'download_url' => route('reports.attachments.download', ['report' => $this->report_id, 'attachment' => $this->id]),
            'mime_type' => $this->mime_type,
            'size' => (int) $this->size,
            'is_archive' => (bool) $this->is_archive,
            'is_image' => str_starts_with((string) $this->mime_type, 'image/'),
        ];
    }
}
