<?php

declare(strict_types=1);

namespace App\DTOs\Messaging;

use App\Enums\ConversationType;

final readonly class ConversationDTO
{
    /**
     * @param  list<int>  $participantUserIds
     */
    public function __construct(
        public ConversationType $type,
        public ?string $name,
        public array $participantUserIds,
        public ?int $tenantId = null,
        public ?int $businessInfoId = null,
    ) {}
}
