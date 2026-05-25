<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MessageSent;

final class HandleMessageSent
{
    public function handle(MessageSent $event): void
    {
        // Attachment processing and outbound notifications are dispatched from MessageService.
    }
}
