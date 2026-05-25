<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_name' => 'Nigeria',
            'country_iso_code' => 'NG',
            'country_is_active' => true,
            'country_sort_order' => 1,
            'state_name' => fake()->state(),
            'state_slug' => fake()->slug(),
            'city_name' => fake()->city(),
            'lga_name' => fake()->word(),
            'lga_slug' => fake()->slug(),
            'vendor_count' => fake()->numberBetween(1, 50),
            'google_place_id' => 'place_' . fake()->md5(),
            'google_resource_name' => 'places/' . fake()->md5(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'formatted_address' => fake()->address(),
            'viewport_north' => fake()->latitude(),
            'viewport_south' => fake()->latitude(),
            'viewport_east' => fake()->longitude(),
            'viewport_west' => fake()->longitude(),
            'address_components_json' => json_encode([]),
        ];
    }
}
