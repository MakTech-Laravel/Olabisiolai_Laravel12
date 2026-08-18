<?php

namespace App\Console\Commands;

use App\Enums\CmsPageType;
use App\Services\CmsPageService;
use Database\Seeders\CmsPageSeeder;
use Illuminate\Console\Command;

class SyncPrivacyPolicyCommand extends Command
{
    protected $signature = 'cms:sync-privacy';

    protected $description = 'Upsert only the Privacy Policy CMS page from the seeded HTML (does not change About or Terms)';

    public function handle(CmsPageService $cms): int
    {
        $page = CmsPageSeeder::privacyPolicyPage();

        $cms->upsertByType(CmsPageType::PrivacyPolicy, $page['title'], $page['description']);

        $this->info('Privacy Policy CMS page updated.');

        return self::SUCCESS;
    }
}
