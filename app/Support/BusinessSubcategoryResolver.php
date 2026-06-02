<?php

namespace App\Support;

use App\Models\Category;

class BusinessSubcategoryResolver
{
    /**
     * Resolve a business subcategory from explicit input or services_offered names.
     *
     * @param  list<string>  $servicesOffered
     */
    public static function resolve(?string $subcategory, int $categoryId, array $servicesOffered = []): ?string
    {
        $category = Category::query()->find($categoryId, ['id', 'subcategories']);
        if ($category === null) {
            return self::normalizeExplicit($subcategory);
        }

        $allowed = is_array($category->subcategories) ? $category->subcategories : [];

        return self::resolveFromLookup(
            $subcategory,
            self::normalizedLookup($allowed),
            $servicesOffered,
        );
    }

    /**
     * @param  list<string>  $allowedSubcategories
     * @return array<string, string> lowercase label => canonical label
     */
    public static function normalizedLookup(array $allowedSubcategories): array
    {
        $lookup = [];

        foreach ($allowedSubcategories as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $canonical = trim($candidate);
            if ($canonical === '') {
                continue;
            }

            $lookup[strtolower($canonical)] = $canonical;
        }

        return $lookup;
    }

    /**
     * @param  array<string, string>  $lookup
     * @param  list<string>  $servicesOffered
     */
    public static function resolveFromLookup(
        ?string $subcategory,
        array $lookup,
        array $servicesOffered,
    ): ?string {
        $explicit = self::normalizeExplicit($subcategory);
        if ($explicit !== null) {
            return $lookup[strtolower($explicit)] ?? $explicit;
        }

        if ($lookup === []) {
            return null;
        }

        foreach ($servicesOffered as $service) {
            if (! is_string($service)) {
                continue;
            }

            $key = strtolower(trim($service));
            if ($key !== '' && isset($lookup[$key])) {
                return $lookup[$key];
            }
        }

        return null;
    }

    private static function normalizeExplicit(?string $subcategory): ?string
    {
        if (! is_string($subcategory)) {
            return null;
        }

        $trimmed = trim($subcategory);

        return $trimmed !== '' ? $trimmed : null;
    }
}
