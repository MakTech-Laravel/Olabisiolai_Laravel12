<?php

namespace App\Support;

class PhoneNormalizer
{
    /**
     * Normalize a phone number to Termii-friendly E.164 digits (no leading +).
     * Defaults to Nigerian (+234) formatting when no country code is present.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '234' . substr($digits, 1);
        }

        if (str_starts_with($digits, '234') && strlen($digits) >= 13) {
            return $digits;
        }

        if (strlen($digits) === 10) {
            return '234' . $digits;
        }

        return $digits;
    }

    public static function mask(?string $phone): string
    {
        $normalized = self::normalize($phone);

        if (! $normalized || strlen($normalized) < 7) {
            return '***';
        }

        $visiblePrefix = substr($normalized, 0, 4);
        $visibleSuffix = substr($normalized, -3);

        return $visiblePrefix . '***' . $visibleSuffix;
    }

    public static function variants(?string $phone): array
    {
        $normalized = self::normalize($phone);

        if (! $normalized) {
            return [];
        }

        $variants = [$normalized];

        if (str_starts_with($normalized, '234')) {
            $local = '0' . substr($normalized, 3);
            $variants[] = $local;
            $variants[] = '+' . $normalized;
        }

        return array_values(array_unique($variants));
    }
}
