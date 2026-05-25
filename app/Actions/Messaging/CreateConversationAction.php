<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\DTOs\Messaging\ConversationDTO;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;

final readonly class CreateConversationAction
{
    public function __construct(
        private ConversationService $conversations,
    ) {}

    public function execute(ConversationDTO $dto, User $creator): Conversation
    {
        return $this->conversations->createConversation($dto, $creator);
    }
}
