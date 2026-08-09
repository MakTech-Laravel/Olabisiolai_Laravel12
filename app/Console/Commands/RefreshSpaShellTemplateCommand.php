<?php

namespace App\Console\Commands;

use App\Services\SpaShellTemplateService;
use Illuminate\Console\Command;

class RefreshSpaShellTemplateCommand extends Command
{
    protected $signature = 'seo:refresh-spa-shell-template';

    protected $description = 'Fetch and cache the SPA index.html used by GET /spa-shell meta injection';

    public function handle(SpaShellTemplateService $templates): int
    {
        if ($templates->refreshRemoteTemplate()) {
            $this->info('SPA shell template refreshed and cached.');

            return self::SUCCESS;
        }

        $this->error('Failed to fetch SPA shell template. Check FRONTEND_URL / SPA_SHELL_TEMPLATE_URL and that /index.html is served statically.');

        return self::FAILURE;
    }
}
