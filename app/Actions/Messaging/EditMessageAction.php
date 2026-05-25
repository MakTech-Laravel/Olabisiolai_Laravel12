<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Message;
use App\Models\User;
use App\Services\MessageService;

final readonly class EditMessageAction
{
    public function __construct(
        private MessageService $messages,
    ) {}

    public function execute(string $messageUuid, string $body, User $user): Message
    {
        return $this->messages->editMessage($messageUuid, $body, $user);
    }
}
