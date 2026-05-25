<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ConversationNotFoundException extends NotFoundHttpException
{
    public static function forUuid(string $uuid): self
    {
        return new self('Conversation not found.');
    }
}
