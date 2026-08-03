<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

class RefreshSitemapCommand extends Command
{
    protected $signature = 'sitemap:refresh';

    protected $description = 'Flush sitemap HTTP cache and regenerate storage/app/sitemap/sitemap.xml';

    public function handle(SitemapService $sitemapService): int
    {
        $result = $sitemapService->refresh();

        $this->info(sprintf(
            'Sitemap refreshed at %s (%d urls, %d chunk(s)).',
            $result['path'],
            $result['urls'],
            $result['chunks'],
        ));

        return self::SUCCESS;
    }
}
