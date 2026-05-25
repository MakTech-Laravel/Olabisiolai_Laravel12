<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\User;
use App\Services\MessageService;

final readonly class MarkAsReadAction
{
    public function __construct(
        private MessageService $messages,
    ) {}

    public function execute(string $messageUuid, User $user): void
    {
        $this->messages->markAsRead($messageUuid, $user);
    }
}
