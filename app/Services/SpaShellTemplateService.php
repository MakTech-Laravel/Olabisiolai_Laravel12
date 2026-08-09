<?php

namespace App\Services;

use App\Support\FrontendUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Load the real Vite SPA index.html for meta injection.
 *
 * Prefer a mounted/local file (SPA_SHELL_INDEX_PATH). Otherwise fetch
 * FRONTEND_URL/index.html (served as a static file by SPA nginx, not via /spa-shell).
 */
class SpaShellTemplateService
{
    public function load(): string
    {
        $configured = trim((string) config('seo.spa_shell_index_path', ''));
        if ($configured !== '' && is_file($configured)) {
            return (string) File::get($configured);
        }

        $cached = $this->cachedRemoteTemplate();
        if ($cached !== null) {
            return $cached;
        }

        return $this->fallbackTemplate();
    }

    /**
     * Force-refresh the remote template cache. Returns true when HTML was stored.
     */
    public function refreshRemoteTemplate(): bool
    {
        $html = $this->fetchRemoteTemplate();
        if ($html === null) {
            return false;
        }

        Cache::put(
            $this->templateCacheKey(),
            $html,
            max(60, (int) config('seo.spa_shell_template_cache_ttl', 3600))
        );

        return true;
    }

    public function forgetRemoteTemplateCache(): void
    {
        Cache::forget($this->templateCacheKey());
    }

    private function cachedRemoteTemplate(): ?string
    {
        if (! (bool) config('seo.spa_shell_template_fetch', true)) {
            return null;
        }

        $ttl = max(60, (int) config('seo.spa_shell_template_cache_ttl', 3600));
        $key = $this->templateCacheKey();

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $html = $this->fetchRemoteTemplate();
        if ($html === null) {
            return null;
        }

        Cache::put($key, $html, $ttl);

        return $html;
    }

    private function fetchRemoteTemplate(): ?string
    {
        $url = trim((string) config('seo.spa_shell_template_url', ''));
        if ($url === '') {
            $url = FrontendUrl::to('/index.html');
        }

        try {
            $response = Http::timeout((int) config('seo.spa_shell_template_fetch_timeout', 5))
                ->accept('text/html')
                ->get($url);

            if (! $response->successful()) {
                Log::warning('SPA shell template fetch failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = (string) $response->body();
            if ($body === '' || stripos($body, '<html') === false) {
                Log::warning('SPA shell template fetch returned non-HTML', ['url' => $url]);

                return null;
            }

            return $body;
        } catch (Throwable $e) {
            Log::warning('SPA shell template fetch exception', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fallbackTemplate(): string
    {
        $fallback = resource_path('spa/index.html');
        if (is_file($fallback)) {
            return (string) File::get($fallback);
        }

        return '<!doctype html><html><head><title>Gidira</title></head><body><div id="root"></div></body></html>';
    }

    private function templateCacheKey(): string
    {
        return (string) config('seo.spa_shell_template_cache_key', 'seo:spa-shell-template');
    }
}
