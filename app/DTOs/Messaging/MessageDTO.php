<?php

declare(strict_types=1);

namespace App\DTOs\Messaging;

final readonly class MessageDTO
{
    /**
     * @param  list<int>  $attachmentIds
     */
    public function __construct(
        public string $conversationUuid,
        public ?string $body,
        public ?int $parentId,
        public array $attachmentIds,
        public ?int $tenantId = null,
    ) {}
}
