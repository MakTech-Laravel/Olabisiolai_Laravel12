<?php

declare(strict_types=1);

namespace App\Contracts\Messaging;

use App\Models\Attachment;

interface AttachmentScannerInterface
{
    /**
     * @throws \RuntimeException when the scan provider rejects the file
     */
    public function assertClean(Attachment $attachment): void;
}
