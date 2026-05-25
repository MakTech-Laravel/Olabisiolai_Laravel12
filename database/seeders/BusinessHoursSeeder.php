<?php

namespace Database\Seeders;

use App\Models\BusinessInfo;
use App\Services\BusinessHoursService;
use Illuminate\Database\Seeder;

class BusinessHoursSeeder extends Seeder
{
    /**
     * Ensure every business profile has a full weekly hours schedule.
     */
    public function run(): void
    {
        $hoursService = app(BusinessHoursService::class);

        $count = 0;

        BusinessInfo::query()
            ->orderBy('id')
            ->each(function (BusinessInfo $business) use ($hoursService, &$count): void {
                $hoursService->syncForBusiness(
                    $business,
                    $hoursService->demoScheduleForBusiness($business),
                );

                $count++;
            });

        $this->command?->info("Seeded business hours for {$count} business profile(s).");
    }
}
