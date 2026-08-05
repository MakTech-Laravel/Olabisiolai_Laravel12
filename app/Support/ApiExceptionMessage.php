<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ApiExceptionMessage
{
    /**
     * User-facing API error text. Never returns filesystem paths or raw engine errors.
     */
    public static function for(Throwable $e, string $fallback = 'Something went wrong. Please try again.'): string
    {
        if ($e instanceof ValidationException) {
            $first = collect($e->errors())->flatten()->first();

            return is_string($first) && trim($first) !== ''
                ? trim($first)
                : 'Please check your input and try again.';
        }

        $message = trim($e->getMessage());
        if ($message === '') {
            return $fallback;
        }

        if (self::looksInternal($message)) {
            return self::mapUploadFailure($message) ?? $fallback;
        }

        if ($e instanceof InvalidArgumentException || $e instanceof RuntimeException) {
            return $message;
        }

        return $fallback;
    }

    public static function looksInternal(string $message): bool
    {
        return (bool) preg_match(
            '/fopen|Permission denied|Failed to open stream|Unable to open|createStreamFromFile|stack trace|SQLSTATE|Connection:|\\\\Users\\\\|[A-Za-z]:\\\\|\/vendor\/|\/home\/|on line \d+|php_network_getaddresses|cURL error/i',
            $message,
        );
    }

    private static function mapUploadFailure(string $message): ?string
    {
        if (preg_match('/fopen|Permission denied|Failed to open stream|Unable to open|createStreamFromFile/i', $message)) {
            return 'We could not process the uploaded file. Please try again with a JPG, PNG, or WebP image under 10MB.';
        }

        return null;
    }
}
