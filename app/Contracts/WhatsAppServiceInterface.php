<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

interface WhatsAppServiceInterface
{
    public function isConfigured(): bool;

    public function resolveDestination(User $vendor): ?string;

    /**
     * Notify-only alert (no message body). Returns false when delivery failed.
     */
    public function sendNewMessageAlert(User $vendor, string $senderName, string $actionUrl): bool;
}
