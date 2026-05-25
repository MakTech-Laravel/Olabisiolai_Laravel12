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

            if (! preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://'.ltrim($url, '/');
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
}
