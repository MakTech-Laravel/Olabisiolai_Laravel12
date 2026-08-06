<?php

namespace App\Services;

use App\Models\SeoPage;
use Illuminate\Support\Facades\Cache;

class SeoPageService
{
    public function findByPath(?string $path): ?SeoPage
    {
        $normalized = SeoPage::normalizePath($path);

        return SeoPage::query()->where('path', $normalized)->first();
    }

    public function shellCacheKey(string $normalizedPath): string
    {
        return config('seo.spa_shell_cache_prefix', 'seo:shell:').$normalizedPath;
    }

    public function forgetShellCacheForPath(?string $path): void
    {
        $normalized = SeoPage::normalizePath($path);
        Cache::forget($this->shellCacheKey($normalized));
    }
}
