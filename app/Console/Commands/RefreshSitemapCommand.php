<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

class RefreshSitemapCommand extends Command
{
    protected $signature = 'sitemap:refresh';

    protected $description = 'Rebuild and warm the general sitemap cache';

    public function handle(SitemapService $sitemapService): int
    {
        $sitemapService->refresh();

        $this->info(sprintf(
            'Sitemap cache refreshed (%d urls).',
            $sitemapService->urlCount(),
        ));

        return self::SUCCESS;
    }
}
