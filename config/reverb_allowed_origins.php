<?php

declare(strict_types=1);

/**
 * Reverb compares WebSocket Origin hosts only (see Pusher\Server::verifyOrigin).
 * Use host patterns here — not full URLs like http://localhost:5173.
 */
return (static function (): array {
    if (in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)) {
        return ['*'];
    }

    $hosts = [];

    foreach (array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))) as $origin) {
        $host = parse_url($origin, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $hosts[] = $host;
        }
    }

    $frontend = trim((string) env('FRONTEND_URL', ''));
    if ($frontend !== '') {
        $host = parse_url($frontend, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $hosts[] = $host;
        }
    }

    $hosts = array_values(array_unique($hosts));

    return $hosts !== [] ? $hosts : ['*'];
})();
