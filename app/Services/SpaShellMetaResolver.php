<?php

namespace App\Services;

use App\Models\BusinessCatalogItem;
use App\Models\BusinessInfo;
use App\Models\SeoPage;
use App\Support\EncryptId;
use App\Support\SpaShellMeta;

/**
 * Resolve per-path SEO meta for spa-shell injection.
 * Order: seo_pages → marketplace business → discoverable catalog item.
 */
class SpaShellMetaResolver
{
    public function __construct(
        private readonly SeoPageService $seoPages,
        private readonly BusinessInfoService $businessInfoService,
        private readonly BusinessCatalogService $businessCatalogService,
    ) {}

    public function resolve(string $normalizedPath): ?SpaShellMeta
    {
        $page = $this->seoPages->findByPath($normalizedPath);
        if ($page instanceof SeoPage) {
            $title = $this->plainText($page->meta_title);
            if ($title === '') {
                $title = $this->plainText($page->page_name) ?: 'Gidira';
            }

            return new SpaShellMeta(
                title: $title,
                description: $this->nullablePlain($page->meta_description),
                keywords: $this->nullablePlain($page->meta_keywords),
            );
        }

        if (preg_match('#^/businesses/([^/]+)$#', $normalizedPath, $m) === 1) {
            return $this->resolveBusiness($m[1]);
        }

        if (preg_match('#^/catalog/items/(\d+)$#', $normalizedPath, $m) === 1) {
            return $this->resolveCatalogItem((int) $m[1]);
        }

        return null;
    }

    private function resolveBusiness(string $slug): ?SpaShellMeta
    {
        $id = EncryptId::decrypt($slug);
        if ($id === null) {
            return null;
        }

        /** @var BusinessInfo|null $business */
        $business = $this->businessInfoService
            ->publicMarketplaceBusinessesQuery()
            ->whereKey($id)
            ->first(['id', 'business_name', 'business_description']);

        if ($business === null) {
            return null;
        }

        $name = $this->plainText($business->business_name) ?: 'Business';

        return new SpaShellMeta(
            title: $name.' | Gidira',
            description: $this->nullablePlain($business->business_description),
        );
    }

    private function resolveCatalogItem(int $id): ?SpaShellMeta
    {
        /** @var BusinessCatalogItem|null $item */
        $item = $this->businessCatalogService
            ->discoverableItemsQuery()
            ->whereKey($id)
            ->first(['id', 'name', 'description']);

        if ($item === null) {
            return null;
        }

        $name = $this->plainText($item->name) ?: 'Catalog item';

        return new SpaShellMeta(
            title: $name.' | Gidira',
            description: $this->nullablePlain($item->description),
        );
    }

    private function plainText(?string $value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function nullablePlain(?string $value): ?string
    {
        $text = $this->plainText($value);

        return $text === '' ? null : $text;
    }
}
