<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class MediaPathGuard
{
    public static function disk(): string
    {
        return (string) config('media.disk', 'public');
    }

    public static function exists(?string $path): bool
    {
        $normalized = is_string($path) ? trim($path) : '';
        if ($normalized === '') {
            return false;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return true;
        }

        return Storage::disk(self::disk())->exists($normalized);
    }

    /**
     * @param  list<string|null>|array<int, string|null>  $paths
     * @return list<string>
     */
    public static function existingPaths(array $paths): array
    {
        $kept = [];

        foreach ($paths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $normalized = trim($path);
            if ($normalized === '' || ! self::exists($normalized)) {
                continue;
            }

            $kept[] = $normalized;
        }

        return array_values(array_unique($kept));
    }

    /**
     * @param  list<string|null>|array<int, string|null>  $paths
     * @return list<array{path: string, url: string|null, missing: bool}>
     */
    public static function describePaths(array $paths): array
    {
        $items = [];

        foreach ($paths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $normalized = trim($path);
            if ($normalized === '') {
                continue;
            }

            $missing = ! self::exists($normalized);
            $items[] = [
                'path' => $normalized,
                'url' => $missing ? null : public_media_url($normalized, null),
                'missing' => $missing,
            ];
        }

        return $items;
    }
}
