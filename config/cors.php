<?php

return [

    'paths' => [
        'api/*',
        'auth/*',
        'oauth/*',
        'broadcasting/auth',
        'login',
        'logout',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_unique(array_merge(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
        [
            env('FRONTEND_URL'),
            env('APP_URL'),
        ],
    )))),

    'allowed_origins_patterns' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS_PATTERNS', '')))
    )),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
