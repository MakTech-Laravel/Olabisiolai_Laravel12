<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessAttachment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $attachmentId,
    ) {
    }

    public function handle(AttachmentService $attachments): void
    {
        $attachment = Attachment::query()->find($this->attachmentId);

        if ($attachment === null) {
            return;
        }

        $attachments->generateThumbnail($attachment);
    }
}
