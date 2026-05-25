<?php

declare(strict_types=1);

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    /*
     * Production (split domains): set in Coolify .env, e.g.
     *   CORS_ALLOWED_ORIGINS=https://olabisiolai-frontend.maktechlaravel.cloud
     *   FRONTEND_URL=https://olabisiolai-frontend.maktechlaravel.cloud
     * Then: php artisan config:clear
     */
    'allowed_origins' => (static function (): array {
        $raw = trim((string) env('CORS_ALLOWED_ORIGINS', ''));

        if ($raw !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        $frontend = trim((string) env('FRONTEND_URL', ''));
        $fromEnv = $frontend !== '' ? [$frontend] : [];

        if (in_array(env('APP_ENV'), ['local', 'testing'], true)) {
            $dev = [
                'http://localhost:5173',
                'http://127.0.0.1:5173',
                'http://localhost:5174',
                'http://127.0.0.1:5174',
                'http://localhost:5175',
                'http://127.0.0.1:5175',
                'http://localhost:3000',
                'http://127.0.0.1:3000',
            ];

            return array_values(array_unique(array_filter(array_merge($fromEnv, $dev))));
        }

        return $fromEnv;
    })(),

    /*
     * When Vite picks the next free port (5174, 5175, …), still allow the SPA origin in local dev.
     */
    'allowed_origins_patterns' => in_array(env('APP_ENV'), ['local', 'testing'], true)
        ? ['#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#']
        : [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
     * Bearer token SPA: keep false. Only set true if VITE_AUTH_STRATEGY=http_only_cookie.
     */
    'supports_credentials' => false,

];
