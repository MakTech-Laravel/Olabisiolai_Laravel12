<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Support\NigeriaLocationCatalog;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Seed all Nigerian states and LGAs for the admin locations panel.
     */
    public function run(): void
    {
        $catalog = new NigeriaLocationCatalog;
        $rows = $catalog->locationRows();

        foreach (array_chunk($rows, 100) as $chunk) {
            Location::query()->upsert(
                $chunk,
                ['lga_slug'],
                [
                    'country_name',
                    'country_iso_code',
                    'country_is_active',
                    'country_sort_order',
                    'state_name',
                    'state_slug',
                    'city_name',
                    'city_slug',
                    'lga_name',
                    'latitude',
                    'longitude',
                    'formatted_address',
                    'updated_at',
                ],
            );
        }

        $this->command?->info(sprintf(
            'Seeded %d Nigerian LGAs across %d states.',
            $catalog->totalLgaCount(),
            count($catalog->stateLgas()),
        ));
    }
}
