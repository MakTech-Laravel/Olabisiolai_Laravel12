<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class UnauthorizedMessageException extends AccessDeniedHttpException
{
    public static function make(): self
    {
        return new self('You are not allowed to perform this action on this message.');
    }
}
