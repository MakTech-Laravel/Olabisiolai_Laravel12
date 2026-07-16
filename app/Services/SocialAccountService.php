<?php

namespace App\Services;

use App\Enums\SocialPlatform;
use Illuminate\Validation\ValidationException;

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

        foreach ($accounts as $index => $account) {
            if (! is_array($account)) {
                continue;
            }

            $platform = strtolower(trim((string) ($account['platform'] ?? '')));
            $url = trim((string) ($account['url'] ?? ''));

            if ($platform === '' || $url === '' || ! in_array($platform, $allowed, true)) {
                continue;
            }

            if (! $this->platformAllowsHandle($platform) && ! $this->looksLikeProfileUrl($url)) {
                throw ValidationException::withMessages([
                    "social_accounts.{$index}.url" => [
                        $this->platformLabel($platform).' requires a full profile link (URL), not a username.',
                    ],
                ]);
            }

            $url = $this->normalizeUrl($platform, $url);

            if ($url === '') {
                throw ValidationException::withMessages([
                    "social_accounts.{$index}.url" => [
                        $this->platformAllowsHandle($platform)
                            ? 'Enter a valid Instagram @handle or profile link.'
                            : 'Enter a valid profile link (URL) for '.$this->platformLabel($platform).'.',
                    ],
                ]);
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

    private function platformAllowsHandle(string $platform): bool
    {
        return $platform === SocialPlatform::Instagram->value;
    }

    private function looksLikeProfileUrl(string $raw): bool
    {
        $trimmed = trim($raw);

        if ($trimmed === '' || str_starts_with($trimmed, '@')) {
            return false;
        }

        if (preg_match('/^https?:\/\//i', $trimmed)) {
            return true;
        }

        $withoutScheme = ltrim(preg_replace('/^https?:\/\//i', '', $trimmed) ?? $trimmed, '/');

        return (bool) preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(\/|\?|#|$)/i', $withoutScheme);
    }

    private function platformLabel(string $platform): string
    {
        return match ($platform) {
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'x' => 'X (Twitter)',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
            'pinterest' => 'Pinterest',
            'threads' => 'Threads',
            'snapchat' => 'Snapchat',
            default => ucfirst($platform),
        };
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

        if (! $this->platformAllowsHandle($platform)) {
            return '';
        }

        $handle = ltrim(trim($raw), '@/');
        $handle = rtrim($handle, '/');

        if ($handle === '' || str_contains($handle, ' ')) {
            return '';
        }

        return match ($platform) {
            'instagram' => "https://instagram.com/{$handle}",
            default => '',
        };
    }
}
