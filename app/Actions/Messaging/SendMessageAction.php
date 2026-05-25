<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\DTOs\Messaging\MessageDTO;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageService;

final readonly class SendMessageAction
{
    public function __construct(
        private MessageService $messages,
    ) {}

    public function execute(MessageDTO $dto, User $sender): Message
    {
        return $this->messages->sendMessage($dto, $sender);
    }
}
