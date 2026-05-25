<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MessageRead;

final class HandleMessageRead
{
    public function handle(MessageRead $event): void
    {
        // Read receipts are persisted in MessageService; use this hook for analytics or auditing.
    }
}
