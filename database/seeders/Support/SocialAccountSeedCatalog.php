<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Str;

/**
 * Dummy social profile links for marketplace seed data.
 */
final class SocialAccountSeedCatalog
{
    /**
     * @return list<array{platform: string, url: string}>
     */
    public static function forBusiness(string $businessName): array
    {
        $handle = Str::slug(Str::before($businessName, ' '));
        if ($handle === '') {
            $handle = 'gidira-business';
        }

        return match ($businessName) {
            'Vision Events & Decor' => self::set($handle, ['instagram', 'facebook', 'tiktok']),
            'Sparkle Clean Services' => self::set($handle, ['instagram', 'facebook']),
            'Royal Catering & Events' => self::set($handle, ['instagram', 'facebook', 'youtube']),
            'Glamour Beauty Spa' => self::set($handle, ['instagram', 'tiktok']),
            'Tech Solutions Pro' => self::set($handle, ['x', 'linkedin', 'youtube']),
            'Premium Plumbing Services' => self::set($handle, ['facebook']),
            'Midnight Mixology Lounge' => self::set($handle, ['instagram', 'tiktok']),
            default => self::set($handle, ['instagram', 'facebook']),
        };
    }

    /**
     * @param  list<string>  $platforms
     * @return list<array{platform: string, url: string}>
     */
    private static function set(string $handle, array $platforms): array
    {
        $accounts = [];

        foreach ($platforms as $platform) {
            $url = self::profileUrl($platform, $handle);
            if ($url === null) {
                continue;
            }

            $accounts[] = [
                'platform' => $platform,
                'url' => $url,
            ];
        }

        return $accounts;
    }

    private static function profileUrl(string $platform, string $handle): ?string
    {
        return match ($platform) {
            'instagram' => "https://instagram.com/{$handle}",
            'facebook' => "https://facebook.com/{$handle}",
            'x' => "https://x.com/{$handle}",
            'linkedin' => "https://linkedin.com/company/{$handle}",
            'tiktok' => "https://tiktok.com/@{$handle}",
            'youtube' => "https://youtube.com/@{$handle}",
            'pinterest' => "https://pinterest.com/{$handle}",
            'threads' => "https://threads.net/@{$handle}",
            'snapchat' => "https://snapchat.com/add/{$handle}",
            default => null,
        };
    }
}
