<?php

namespace App\Support;

/**
 * PHP port of olabisiolai_frontend_react/src/lib/encryptId.ts.
 * Produces base64url tokens with the same g_ prefix so sitemap /businesses/{slug}
 * URLs match SPA share links byte-for-byte.
 */
final class EncryptId
{
    private const PREFIX = 'g_';

    /**
     * Mirror of encryptId(id) in encryptId.ts:
     * btoa(`g_${id}`) → strip `=` → `+`→`-` → `/`→`_`.
     */
    public static function encrypt(int|string $id): string
    {
        $raw = base64_encode(self::PREFIX.(string) $id);

        return strtr(rtrim($raw, '='), '+/', '-_');
    }

    /**
     * Mirror of decryptId(slug) for tests / resolution.
     */
    public static function decrypt(string $slug): ?int
    {
        $trimmed = trim($slug);
        if ($trimmed === '') {
            return null;
        }

        $b64 = strtr($trimmed, '-_', '+/');
        $pad = (4 - (strlen($b64) % 4)) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', $pad);
        }

        $decoded = base64_decode($b64, true);
        if ($decoded === false || ! str_starts_with($decoded, self::PREFIX)) {
            return null;
        }

        $n = (int) substr($decoded, strlen(self::PREFIX));

        return $n > 0 ? $n : null;
    }
}
