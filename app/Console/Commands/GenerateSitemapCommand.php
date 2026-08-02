<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Build the public SPA sitemap XML under storage/app/sitemap';

    public function handle(SitemapService $sitemapService): int
    {
        $result = $sitemapService->generate();

        $this->info(sprintf(
            'Sitemap written to %s (%d urls, %d chunk(s)).',
            $result['path'],
            $result['urls'],
            $result['chunks'],
        ));

        return self::SUCCESS;
    }
}
