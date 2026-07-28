<?php

namespace App\Support;

/**
 * Catalog list vs sale pricing.
 *
 * Write API: `price_kobo` is the list/base amount the vendor enters.
 * Stored/read API: `price_kobo` is the payable (sale) amount; `original_price_kobo` is the struck-through list.
 * No "X% off" labels — UI shows dual amounts only.
 */
final class CatalogPricing
{
    /**
     * @param  array{
     *     price_kobo?: int|null,
     *     price_from?: bool,
     *     discount_type?: string|null,
     *     discount_value?: int|null,
     * }  $input
     * @return array{
     *     price_kobo: int|null,
     *     original_price_kobo: int|null,
     *     discount_type: string|null,
     *     discount_value: int|null,
     *     has_discount: bool,
     * }
     */
    public static function resolveStoredPrices(array $input): array
    {
        $listKobo = array_key_exists('price_kobo', $input) && $input['price_kobo'] !== null
            ? (int) $input['price_kobo']
            : null;
        $priceFrom = (bool) ($input['price_from'] ?? false);
        $type = isset($input['discount_type']) && is_string($input['discount_type'])
            ? strtolower(trim($input['discount_type']))
            : null;
        $value = array_key_exists('discount_value', $input) && $input['discount_value'] !== null
            ? (int) $input['discount_value']
            : null;

        if ($type === '') {
            $type = null;
        }

        if ($type === null || $value === null || $value < 1 || $listKobo === null || $priceFrom) {
            return [
                'price_kobo' => $listKobo,
                'original_price_kobo' => null,
                'discount_type' => null,
                'discount_value' => null,
                'has_discount' => false,
            ];
        }

        if (! in_array($type, ['percent', 'flat'], true)) {
            return [
                'price_kobo' => $listKobo,
                'original_price_kobo' => null,
                'discount_type' => null,
                'discount_value' => null,
                'has_discount' => false,
            ];
        }

        $saleKobo = self::salePriceKobo($listKobo, $type, $value);
        if ($saleKobo === null || $saleKobo >= $listKobo) {
            return [
                'price_kobo' => $listKobo,
                'original_price_kobo' => null,
                'discount_type' => null,
                'discount_value' => null,
                'has_discount' => false,
            ];
        }

        return [
            'price_kobo' => $saleKobo,
            'original_price_kobo' => $listKobo,
            'discount_type' => $type,
            'discount_value' => $value,
            'has_discount' => true,
        ];
    }

    public static function salePriceKobo(int $listKobo, string $type, int $value): ?int
    {
        if ($listKobo < 0 || $value < 1) {
            return null;
        }

        if ($type === 'percent') {
            if ($value > 100) {
                return null;
            }

            return (int) round($listKobo * (100 - $value) / 100);
        }

        if ($type === 'flat') {
            if ($value > $listKobo) {
                return null;
            }

            return max(0, $listKobo - $value);
        }

        return null;
    }

    /**
     * List/base amount for vendor edit forms (original when discounted, else price_kobo).
     */
    public static function listPriceKobo(?int $priceKobo, ?int $originalPriceKobo, bool $hasDiscount): ?int
    {
        if ($hasDiscount && $originalPriceKobo !== null) {
            return $originalPriceKobo;
        }

        return $priceKobo;
    }
}
