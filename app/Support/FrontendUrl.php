<?php

namespace App\Support;

final class FrontendUrl
{
    /**
     * Absolute SPA URL from FRONTEND_URL + a relative path.
     */
    public static function to(string $path = '/'): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $path = '/'.ltrim($path, '/');

        if ($path === '/') {
            return $base.'/';
        }

        return $base.$path;
    }
}
