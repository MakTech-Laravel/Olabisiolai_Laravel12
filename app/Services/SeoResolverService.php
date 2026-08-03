<?php

namespace App\Services;

use App\Models\BusinessCatalogItem;
use App\Models\BusinessInfo;
use App\Models\SeoPage;
use App\Support\EncryptId;
use App\Support\FrontendUrl;
use App\Support\ResolvedSeo;

/**
 * Single source of truth for path → SEO (resolve API + spa-shell).
 * Order: seo_pages → marketplace business → discoverable catalog item → defaults.
 */
class SeoResolverService
{
    private const SITE_NAME = 'Gidira';

    private const DEFAULT_DESCRIPTION = 'Discover local businesses and services on Gidira.';

    public function __construct(
        private readonly SeoPageService $seoPages,
        private readonly BusinessInfoService $businessInfoService,
        private readonly BusinessCatalogService $businessCatalogService,
    ) {}

    public function resolve(?string $path): ResolvedSeo
    {
        $normalized = SeoPage::normalizePath($path);

        $page = $this->seoPages->findByPath($normalized);
        if ($page instanceof SeoPage) {
            return $this->fromSeoPage($page, $normalized);
        }

        if (preg_match('#^/businesses/([^/]+)$#', $normalized, $m) === 1) {
            $resolved = $this->fromBusiness($m[1], $normalized);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if (preg_match('#^/catalog/items/(\d+)$#', $normalized, $m) === 1) {
            $resolved = $this->fromCatalogItem((int) $m[1], $normalized);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $this->defaults($normalized);
    }

    private function fromSeoPage(SeoPage $page, string $normalized): ResolvedSeo
    {
        $title = $this->plainText($page->meta_title);
        if ($title === '') {
            $title = $this->plainText($page->page_name) ?: self::SITE_NAME;
        }

        $description = $this->nullablePlain($page->meta_description);
        $keywords = $this->nullablePlain($page->meta_keywords);
        $canonical = $this->canonicalFromOverride($page->canonical_url, $normalized);
        $noindex = (bool) $page->noindex;
        $image = $this->resolveImageUrl($page->og_image);

        $jsonLd = [];
        if ($normalized === '/') {
            $jsonLd = $this->homeJsonLd($canonical, $image);
        }

        return $this->build(
            matchedEntity: 'static',
            title: $title,
            description: $description,
            keywords: $keywords,
            canonical: $canonical,
            noindex: $noindex,
            image: $image,
            ogType: 'website',
            jsonLd: $jsonLd,
        );
    }

    private function fromBusiness(string $slug, string $normalized): ?ResolvedSeo
    {
        $id = EncryptId::decrypt($slug);
        if ($id === null) {
            return null;
        }

        /** @var BusinessInfo|null $business */
        $business = $this->businessInfoService
            ->publicMarketplaceBusinessesQuery()
            ->whereKey($id)
            ->first(['id', 'business_name', 'business_description', 'logo_path']);

        if ($business === null) {
            return null;
        }

        $name = $this->plainText($business->business_name) ?: 'Business';
        $description = $this->nullablePlain($business->business_description);
        $canonical = FrontendUrl::to($normalized);
        $image = $this->resolveImageUrl(
            filled($business->logo_path) ? (string) $business->logo_path : null
        );

        $jsonLd = [[
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $name,
            'url' => $canonical,
            'description' => $description,
            'image' => $image,
        ]];
        $jsonLd[0] = array_filter($jsonLd[0], fn ($v) => $v !== null && $v !== '');

        return $this->build(
            matchedEntity: 'business',
            title: $name.' | '.self::SITE_NAME,
            description: $description,
            keywords: null,
            canonical: $canonical,
            noindex: false,
            image: $image,
            ogType: 'website',
            jsonLd: $jsonLd,
        );
    }

    private function fromCatalogItem(int $id, string $normalized): ?ResolvedSeo
    {
        /** @var BusinessCatalogItem|null $item */
        $item = $this->businessCatalogService
            ->discoverableItemsQuery()
            ->whereKey($id)
            ->first(['id', 'name', 'description', 'type', 'price_kobo', 'image_paths']);

        if ($item === null) {
            return null;
        }

        $name = $this->plainText($item->name) ?: 'Catalog item';
        $description = $this->nullablePlain($item->description);
        $canonical = FrontendUrl::to($normalized);
        $schemaType = strtolower((string) $item->type) === 'service' ? 'Service' : 'Product';

        $image = null;
        $paths = $item->image_paths;
        if (is_array($paths) && isset($paths[0]) && is_string($paths[0]) && $paths[0] !== '') {
            $image = $this->resolveImageUrl($paths[0]);
        }
        if ($image === null) {
            $image = $this->resolveImageUrl(null);
        }

        $node = [
            '@context' => 'https://schema.org',
            '@type' => $schemaType,
            'name' => $name,
            'url' => $canonical,
            'description' => $description,
            'image' => $image,
        ];

        if ($schemaType === 'Product' && $item->price_kobo !== null && (int) $item->price_kobo > 0) {
            $node['offers'] = [
                '@type' => 'Offer',
                'priceCurrency' => 'NGN',
                'price' => number_format(((int) $item->price_kobo) / 100, 2, '.', ''),
                'availability' => 'https://schema.org/InStock',
            ];
        }

        $jsonLd = [array_filter($node, fn ($v) => $v !== null && $v !== '')];

        return $this->build(
            matchedEntity: 'catalog_item',
            title: $name.' | '.self::SITE_NAME,
            description: $description,
            keywords: null,
            canonical: $canonical,
            noindex: false,
            image: $image,
            ogType: 'website',
            jsonLd: $jsonLd,
        );
    }

    private function defaults(string $normalized): ResolvedSeo
    {
        return $this->build(
            matchedEntity: null,
            title: self::SITE_NAME,
            description: self::DEFAULT_DESCRIPTION,
            keywords: null,
            canonical: FrontendUrl::to($normalized),
            noindex: false,
            image: $this->resolveImageUrl(null),
            ogType: 'website',
            jsonLd: [],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $jsonLd
     */
    private function build(
        ?string $matchedEntity,
        string $title,
        ?string $description,
        ?string $keywords,
        string $canonical,
        bool $noindex,
        ?string $image,
        string $ogType,
        array $jsonLd,
    ): ResolvedSeo {
        $robots = $noindex ? 'noindex,nofollow' : 'index,follow';
        $twitterCard = $image ? 'summary_large_image' : 'summary';

        $og = array_filter([
            'title' => $title,
            'description' => $description,
            'type' => $ogType,
            'url' => $canonical,
            'site_name' => self::SITE_NAME,
            'image' => $image,
        ], fn ($v) => $v !== null && $v !== '');

        $twitter = array_filter([
            'card' => $twitterCard,
            'title' => $title,
            'description' => $description,
            'image' => $image,
        ], fn ($v) => $v !== null && $v !== '');

        return new ResolvedSeo(
            matchedEntity: $matchedEntity,
            title: $title,
            description: $description,
            keywords: $keywords,
            canonical: $canonical,
            robots: $robots,
            og: $og,
            twitter: $twitter,
            jsonLd: $jsonLd,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function homeJsonLd(string $canonical, ?string $logo): array
    {
        $org = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => self::SITE_NAME,
            'url' => $canonical,
            'logo' => $logo,
        ], fn ($v) => $v !== null && $v !== '');

        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => self::SITE_NAME,
            'url' => $canonical,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => FrontendUrl::to('/filters').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];

        return [$org, $website];
    }

    private function canonicalFromOverride(?string $override, string $normalized): string
    {
        $trimmed = trim((string) $override);
        if ($trimmed !== '' && filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return $trimmed;
        }

        return FrontendUrl::to($normalized);
    }

    private function resolveImageUrl(?string $pathOrUrl): ?string
    {
        $value = trim((string) $pathOrUrl);
        if ($value !== '') {
            if (preg_match('#^https?://#i', $value) === 1) {
                return $value;
            }
            $url = public_media_url($value, null);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        $default = trim((string) config('seo.og_default_image', ''));
        if ($default !== '') {
            return $default;
        }

        return null;
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
