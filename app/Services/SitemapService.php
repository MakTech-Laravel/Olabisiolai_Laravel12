<?php

namespace App\Services;

use App\Models\CmsPage;
use App\Models\SeoPage;
use App\Support\EncryptId;
use App\Support\FrontendUrl;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapService
{
    public const DISK_DIRECTORY = 'sitemap';

    public const PRIMARY_FILENAME = 'sitemap.xml';

    /**
     * Soft chunk size for a future sitemap-index split. v1 writes a single file
     * regardless; exceeding this only logs intent via chunk keys in buildChunks().
     */
    public const CHUNK_HINT = 45000;

    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
        private readonly BusinessCatalogService $businessCatalogService,
    ) {}

    /**
     * Absolute path to the primary generated sitemap on local disk.
     */
    public function path(?string $filename = null): string
    {
        $filename ??= self::PRIMARY_FILENAME;

        return storage_path('app/'.self::DISK_DIRECTORY.'/'.$filename);
    }

    /**
     * Build URL sets (chunking hook). v1 returns a single "sitemap" entry.
     *
     * @return array<string, Sitemap>
     */
    public function buildChunks(): array
    {
        $generatedAt = Carbon::now();
        $sitemap = Sitemap::create();

        $this->addStaticPaths($sitemap, $generatedAt);
        $this->addBusinesses($sitemap);
        $this->addCatalogItems($sitemap);

        // Future: if tag count > CHUNK_HINT, split into static / businesses / catalog
        // named chunks and return a sitemap-index writer instead.
        return ['sitemap' => $sitemap];
    }

    /**
     * Generate and write sitemap chunk(s) under storage/app/sitemap/.
     *
     * @return array{path: string, urls: int, chunks: int}
     */
    public function generate(): array
    {
        $directory = storage_path('app/'.self::DISK_DIRECTORY);
        File::ensureDirectoryExists($directory);

        $chunks = $this->buildChunks();
        $primaryPath = $this->path(self::PRIMARY_FILENAME);

        if (count($chunks) === 1) {
            $sitemap = reset($chunks);
            $sitemap->writeToFile($primaryPath);

            return [
                'path' => $primaryPath,
                'urls' => $this->countUrlsInFile($primaryPath),
                'chunks' => 1,
            ];
        }

        // Future sitemap-index path: write each chunk file + index as sitemap.xml
        foreach ($chunks as $name => $sitemap) {
            $sitemap->writeToFile($this->path($name.'.xml'));
        }

        return [
            'path' => $primaryPath,
            'urls' => 0,
            'chunks' => count($chunks),
        ];
    }

    public function emptyUrlsetXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            ."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            ."\n"
            .'</urlset>';
    }

    private function addStaticPaths(Sitemap $sitemap, Carbon $generatedAt): void
    {
        /** @var array<string, Carbon> $cmsLastmods */
        $cmsLastmods = CmsPage::query()
            ->get(['type', 'updated_at'])
            ->mapWithKeys(fn (CmsPage $page) => [
                $page->type instanceof \BackedEnum ? $page->type->value : (string) $page->type => $page->updated_at,
            ])
            ->all();

        /** @var \Illuminate\Support\Collection<string, SeoPage> $seoByPath */
        $seoByPath = SeoPage::query()
            ->get(['path', 'updated_at', 'changefreq', 'priority'])
            ->keyBy('path');

        foreach (config('sitemap-urls', []) as $entry) {
            $path = (string) ($entry['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $normalizedPath = SeoPage::normalizePath($path);
            $changefreq = (string) ($entry['changefreq'] ?? Url::CHANGE_FREQUENCY_WEEKLY);
            $priority = (float) ($entry['priority'] ?? 0.7);
            $cmsType = $entry['cms_type'] ?? null;
            $lastmod = $generatedAt;

            if (is_string($cmsType) && isset($cmsLastmods[$cmsType])) {
                $lastmod = Carbon::parse($cmsLastmods[$cmsType]);
            }

            $seoPage = $seoByPath->get($normalizedPath);
            if ($seoPage instanceof SeoPage) {
                $lastmod = Carbon::parse($seoPage->updated_at);
                if (is_string($seoPage->changefreq) && $seoPage->changefreq !== '') {
                    $changefreq = $seoPage->changefreq;
                }
                if ($seoPage->priority !== null) {
                    $priority = (float) $seoPage->priority;
                }
            }

            $sitemap->add(
                Url::create(FrontendUrl::to($path))
                    ->setLastModificationDate($lastmod)
                    ->setChangeFrequency($changefreq)
                    ->setPriority($priority)
            );
        }
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
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                            ->setPriority(0.9)
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
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                            ->setPriority(0.8)
                    );
                }
            }, 'business_catalog_items.id', 'id');
    }

    private function countUrlsInFile(string $path): int
    {
        if (! is_file($path)) {
            return 0;
        }

        $contents = (string) file_get_contents($path);

        return substr_count($contents, '<url>');
    }
}
