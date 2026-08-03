<?php

namespace App\Services;

use App\Models\SeoPage;
use App\Support\EncryptId;
use App\Support\FrontendUrl;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Builds the general marketplace sitemap (ZBC-pattern: in-memory Spatie + Cache::remember).
 *
 * Static URLs come from seo_pages (config/sitemap-urls.php is seeder-only).
 * Dynamic URLs: marketplace-visible businesses + discoverable catalog items.
 * noindex seo_pages rows are omitted — same rule as SeoResolverService robots.
 */
class SitemapService
{
    private const CACHE_GENERAL = 'sitemap:general:xml';

    private const TTL_GENERAL = 3600;

    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
        private readonly BusinessCatalogService $businessCatalogService,
    ) {}

    public function generalXml(): string
    {
        return Cache::remember(self::CACHE_GENERAL, self::TTL_GENERAL, fn () => $this->buildGeneral()->render());
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_GENERAL);
    }

    /**
     * Flush and rebuild the general sitemap cache (command + admin share this).
     */
    public function refresh(): void
    {
        $this->flushCache();
        $this->generalXml();
    }

    public function urlCount(?string $xml = null): int
    {
        $xml ??= $this->generalXml();

        return substr_count($xml, '<url>');
    }

    public function robotsTxt(): string
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
        ];

        foreach (config('seo.robots_disallow', []) as $path) {
            $path = trim((string) $path);
            if ($path !== '') {
                $lines[] = 'Disallow: '.$path;
            }
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.FrontendUrl::to('/sitemap.xml');
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function buildGeneral(): Sitemap
    {
        $sitemap = Sitemap::create();

        $this->addStaticSeoPages($sitemap);
        $this->addBusinesses($sitemap);
        $this->addCatalogItems($sitemap);

        return $sitemap;
    }

    private function addStaticSeoPages(Sitemap $sitemap): void
    {
        SeoPage::query()
            ->where('noindex', false)
            ->orderBy('path')
            ->get(['path', 'updated_at'])
            ->each(function (SeoPage $page) use ($sitemap): void {
                $sitemap->add(
                    Url::create(FrontendUrl::to($page->path))
                        ->setLastModificationDate(Carbon::parse($page->updated_at))
                );
            });
    }

    private function addBusinesses(Sitemap $sitemap): void
    {
        $this->businessInfoService
            ->publicMarketplaceBusinessesQuery()
            ->orderBy('id')
            ->select(['id', 'updated_at'])
            ->chunkById(500, function ($businesses) use ($sitemap): void {
                foreach ($businesses as $business) {
                    $sitemap->add(
                        Url::create(FrontendUrl::to('/businesses/'.EncryptId::encrypt($business->id)))
                            ->setLastModificationDate(Carbon::parse($business->updated_at))
                    );
                }
            });
    }

    private function addCatalogItems(Sitemap $sitemap): void
    {
        $this->businessCatalogService
            ->discoverableItemsQuery()
            ->setEagerLoads([])
            ->reorder()
            ->orderBy('business_catalog_items.id')
            ->select(['business_catalog_items.id', 'business_catalog_items.updated_at'])
            ->chunkById(500, function ($items) use ($sitemap): void {
                foreach ($items as $item) {
                    $sitemap->add(
                        Url::create(FrontendUrl::to('/catalog/items/'.$item->id))
                            ->setLastModificationDate(Carbon::parse($item->updated_at))
                    );
                }
            }, 'business_catalog_items.id', 'id');
    }
}
