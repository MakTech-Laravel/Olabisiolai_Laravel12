<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BusinessStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBusinessSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_compound_search_finds_cleaners_in_target_lga(): void
    {
        $category = Category::factory()->create([
            'name' => 'Home Repair Services',
            'subcategories' => ['Cleaning Services', 'Plumber'],
        ]);
        $surulere = Location::factory()->create(['lga_name' => 'Surulere', 'city_name' => 'Lagos']);
        $ikeja = Location::factory()->create(['lga_name' => 'Ikeja', 'city_name' => 'Lagos']);

        $surulereCleaner = $this->createActiveBusiness($category, $surulere, 'Sparkle Clean Services', [
            'subcategory' => 'Cleaning Services',
            'services_offered' => ['House Cleaning', 'Office Cleaning'],
            'business_description' => 'Professional home and office cleaning.',
        ]);
        $ikejaCleaner = $this->createActiveBusiness($category, $ikeja, 'Ikeja Clean Co', [
            'subcategory' => 'Cleaning Services',
            'services_offered' => ['Deep Cleaning'],
            'business_description' => 'Deep cleaning specialists in Ikeja.',
        ]);
        $surulerePlumber = $this->createActiveBusiness($category, $surulere, 'Surulere Plumbing', [
            'subcategory' => 'Plumber',
            'services_offered' => ['Pipe Repair'],
            'business_description' => 'Emergency plumbing and pipe repair.',
        ]);

        $response = $this->getJson('/api/v1/businesses/home?per_page=20&search=' . urlencode('cleaner in Surulere'));

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertContains($surulereCleaner->id, $ids);
        $this->assertNotContains($ikejaCleaner->id, $ids);
        $this->assertNotContains($surulerePlumber->id, $ids);
    }

    public function test_single_term_search_still_finds_matching_businesses(): void
    {
        $category = Category::factory()->create([
            'name' => 'Home Repair Services',
            'subcategories' => ['Cleaning Services'],
        ]);
        $location = Location::factory()->create(['lga_name' => 'Surulere']);

        $cleaner = $this->createActiveBusiness($category, $location, 'Sparkle Clean Services', [
            'subcategory' => 'Cleaning Services',
            'services_offered' => ['Cleaning Services'],
            'business_description' => 'Affordable cleaning for homes and offices.',
        ]);
        $other = $this->createActiveBusiness($category, $location, 'Other Shop', [
            'subcategory' => 'Others',
            'services_offered' => ['Retail'],
            'business_description' => 'General retail and supplies.',
        ]);

        $response = $this->getJson('/api/v1/businesses/home?per_page=20&search=clean');

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertContains($cleaner->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_location_only_search_returns_all_businesses_in_lga(): void
    {
        $category = Category::factory()->create();
        $surulere = Location::factory()->create(['lga_name' => 'Surulere']);
        $ikeja = Location::factory()->create(['lga_name' => 'Ikeja']);

        $inSurulere = $this->createActiveBusiness($category, $surulere, 'Surulere Shop');
        $inIkeja = $this->createActiveBusiness($category, $ikeja, 'Ikeja Shop');

        $response = $this->getJson('/api/v1/businesses/home?per_page=20&search=Surulere');

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertContains($inSurulere->id, $ids);
        $this->assertNotContains($inIkeja->id, $ids);
    }

    public function test_search_endpoint_supports_compound_queries(): void
    {
        $category = Category::factory()->create([
            'name' => 'Home Repair Services',
            'subcategories' => ['Cleaning Services'],
        ]);
        $surulere = Location::factory()->create(['lga_name' => 'Surulere']);
        $ikeja = Location::factory()->create(['lga_name' => 'Ikeja']);

        $target = $this->createActiveBusiness($category, $surulere, 'Bright Cleaners', [
            'subcategory' => 'Cleaning Services',
            'services_offered' => ['Home Cleaning'],
            'business_description' => 'Residential cleaning in Surulere.',
        ]);
        $other = $this->createActiveBusiness($category, $ikeja, 'Bright Cleaners Ikeja', [
            'subcategory' => 'Cleaning Services',
            'services_offered' => ['Home Cleaning'],
            'business_description' => 'Residential cleaning in Ikeja.',
        ]);

        $response = $this->getJson('/api/v1/businesses/search?query=' . urlencode('cleaning services surulere'));

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertContains($target->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createActiveBusiness(
        Category $category,
        Location $location,
        string $name,
        array $overrides = [],
    ): BusinessInfo {
        $user = User::factory()->create(['role' => 'vendor']);

        return BusinessInfo::factory()->for($user)->create(array_merge([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => $name,
            'business_status' => BusinessStatus::Active,
        ], $overrides));
    }
}
