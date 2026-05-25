<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Contracts\Messaging\AttachmentScannerInterface;
use App\Models\Attachment;

final class NoopAttachmentScanner implements AttachmentScannerInterface
{
    public function assertClean(Attachment $attachment): void
    {
        // Future: integrate ClamAV or a cloud malware API.
    }
}
