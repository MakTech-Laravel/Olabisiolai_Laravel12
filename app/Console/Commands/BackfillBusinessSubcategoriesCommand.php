<?php

namespace App\Console\Commands;

use App\Services\BusinessSubcategoryBackfillService;
use Illuminate\Console\Command;

class BackfillBusinessSubcategoriesCommand extends Command
{
    protected $signature = 'business:backfill-subcategories
                            {--chunk=200 : Number of businesses processed per batch}';

    protected $description = 'Backfill missing business subcategories from services_offered and category catalog';

    public function handle(BusinessSubcategoryBackfillService $backfillService): int
    {
        $chunkSize = max(50, (int) $this->option('chunk'));

        $this->info('Backfilling business subcategories…');

        $result = $backfillService->run($chunkSize);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Scanned', (string) $result->scanned],
                ['Updated', (string) $result->updated],
                ['Skipped', (string) $result->skipped],
            ],
        );

        return self::SUCCESS;
    }
}
