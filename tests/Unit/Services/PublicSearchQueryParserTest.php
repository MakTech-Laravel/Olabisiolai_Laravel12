<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Location;
use App\Services\PublicSearchQueryParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSearchQueryParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_service_and_location_from_natural_query(): void
    {
        $surulere = Location::factory()->create(['lga_name' => 'Surulere', 'city_name' => 'Lagos']);
        Location::factory()->create(['lga_name' => 'Ikeja', 'city_name' => 'Lagos']);

        Category::factory()->create([
            'name' => 'Home Repair Services',
            'subcategories' => ['Cleaning Services', 'Plumber'],
        ]);

        $parsed = app(PublicSearchQueryParser::class)->parse('cleaner in Surulere');

        $this->assertContains($surulere->id, $parsed->locationIds);
        $this->assertTrue($parsed->resolvedLocationFromSearch);
        $this->assertNotEmpty($parsed->serviceTermGroups);
        $this->assertContains('cleaner', $parsed->serviceTermGroups[0]);
        $this->assertContains('cleaning services', $parsed->serviceTermGroups[0]);
    }

    public function test_parses_location_only_query(): void
    {
        $gabasawa = Location::factory()->create(['lga_name' => 'Gabasawa']);

        $parsed = app(PublicSearchQueryParser::class)->parse('Gabasawa');

        $this->assertSame([$gabasawa->id], $parsed->locationIds);
        $this->assertTrue($parsed->hasLocationOnly());
    }

    public function test_parses_service_only_query(): void
    {
        Category::factory()->create([
            'name' => 'Home Repair Services',
            'subcategories' => ['Cleaning Services'],
        ]);

        $parsed = app(PublicSearchQueryParser::class)->parse('clean');

        $this->assertSame([], $parsed->locationIds);
        $this->assertNotEmpty($parsed->serviceTermGroups);
        $this->assertContains('clean', $parsed->serviceTermGroups[0]);
    }
}
