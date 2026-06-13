<?php

namespace App\Services;

use App\Enums\SocialPlatform;

class SocialAccountService
{
    /**
     * @param  array<int, mixed>|null  $accounts
     * @return list<array{platform: string, url: string}>|null
     */
    public function normalizeInput(?array $accounts): ?array
    {
        if ($accounts === null || $accounts === []) {
            return null;
        }

        $allowed = SocialPlatform::values();
        $normalized = [];

        foreach ($accounts as $account) {
            if (! is_array($account)) {
                continue;
            }

            $platform = strtolower(trim((string) ($account['platform'] ?? '')));
            $url = trim((string) ($account['url'] ?? ''));

            if ($platform === '' || $url === '' || ! in_array($platform, $allowed, true)) {
                continue;
            }

            $url = $this->normalizeUrl($platform, $url);

            if ($url === '') {
                continue;
            }

            $normalized[] = [
                'platform' => $platform,
                'url' => $url,
            ];
        }

        if ($normalized === []) {
            return null;
        }

        return array_values($normalized);
    }

    private function normalizeUrl(string $platform, string $raw): string
    {
        if (preg_match('/^https?:\/\//i', $raw)) {
            return $raw;
        }

        $withoutScheme = preg_replace('/^https?:\/\//i', '', $raw) ?? $raw;
        if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}/i', $withoutScheme) && ! str_starts_with($withoutScheme, '@')) {
            return 'https://'.ltrim($withoutScheme, '/');
        }

        $handle = ltrim(trim($raw), '@/');
        $handle = rtrim($handle, '/');

        if ($handle === '' || str_contains($handle, ' ')) {
            return '';
        }

        return match ($platform) {
            'instagram' => "https://instagram.com/{$handle}",
            'facebook' => "https://facebook.com/{$handle}",
            'x' => "https://x.com/{$handle}",
            'linkedin' => "https://linkedin.com/in/{$handle}",
            'tiktok' => "https://tiktok.com/@{$handle}",
            'youtube' => "https://youtube.com/@{$handle}",
            'pinterest' => "https://pinterest.com/{$handle}",
            'threads' => "https://threads.net/@{$handle}",
            'snapchat' => "https://snapchat.com/add/{$handle}",
            default => 'https://'.ltrim($raw, '/'),
        };
    }
}
