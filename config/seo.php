<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SPA shell index.html for SEO meta injection
    |--------------------------------------------------------------------------
    |
    | Prefer a real Vite build output mounted into the API container
    | (SPA_SHELL_INDEX_PATH). If unset/missing, optionally fetch
    | FRONTEND_URL/index.html (static file on SPA nginx — not /spa-shell).
    | Last resort: resources/spa/index.html (tests / local fallback).
    |
    */

    'spa_shell_index_path' => env('SPA_SHELL_INDEX_PATH'),

    'spa_shell_cache_ttl' => (int) env('SPA_SHELL_CACHE_TTL', 86400),

    'spa_shell_cache_prefix' => 'seo:shell:',

    'spa_shell_template_fetch' => (bool) env('SPA_SHELL_TEMPLATE_FETCH', true),

    'spa_shell_template_url' => env('SPA_SHELL_TEMPLATE_URL'),

    'spa_shell_template_cache_ttl' => (int) env('SPA_SHELL_TEMPLATE_CACHE_TTL', 3600),

    'spa_shell_template_cache_key' => 'seo:spa-shell-template',

    'spa_shell_template_fetch_timeout' => (int) env('SPA_SHELL_TEMPLATE_FETCH_TIMEOUT', 5),

    'og_default_image' => env('OG_DEFAULT_IMAGE'),

    'robots_disallow' => [
        '/admin',
        '/admin/',
        '/vendor',
        '/vendor/',
        '/login',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/auth',
        '/auth/',
        '/cart',
        '/messages',
        '/ws-test',
    ],

];
