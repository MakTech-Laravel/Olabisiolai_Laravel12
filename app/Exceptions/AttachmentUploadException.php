<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class AttachmentUploadException extends RuntimeException
{
    public static function invalidMime(string $mime): self
    {
        return new self(sprintf('Unsupported or invalid file type: %s', $mime));
    }

    public static function processingFailed(string $reason): self
    {
        return new self(sprintf('Attachment processing failed: %s', $reason));
    }
}
