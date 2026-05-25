<?php

declare(strict_types=1);

namespace App\DTOs\Messaging;

use App\Enums\AttachmentType;

final readonly class AttachmentDTO
{
    public function __construct(
        public string $fileName,
        public string $filePath,
        public int $fileSize,
        public string $mimeType,
        public AttachmentType $type,
        public ?array $metadata = null,
    ) {}
}
