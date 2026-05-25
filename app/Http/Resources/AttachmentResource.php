<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attachment
 */
final class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AttachmentService $attachments */
        $attachments = app(AttachmentService::class);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'type' => $this->type->value,
            'file_size' => $this->file_size,
            'thumbnail_path' => $this->thumbnail_path,
            'thumbnail_url' => $attachments->temporaryThumbnailUrl($this->resource),
            'url' => $attachments->temporaryUrl($this->resource),
        ];
    }
}
