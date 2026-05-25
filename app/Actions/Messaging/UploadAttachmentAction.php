<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Attachment;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Http\UploadedFile;

final readonly class UploadAttachmentAction
{
    public function __construct(
        private AttachmentService $attachments,
    ) {}

    public function execute(UploadedFile $file, User $uploader): Attachment
    {
        return $this->attachments->upload($file, $uploader);
    }
}
