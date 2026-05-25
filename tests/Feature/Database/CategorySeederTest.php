<?php

namespace Tests\Feature\Database;

use App\Models\BusinessInfo;
use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FeaturedBusinessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_seeder_inserts_all_marketplace_categories_with_subcategories(): void
    {
        $this->seed(CategorySeeder::class);

        $definitions = CategorySeeder::categoryDefinitions();

        $this->assertCount(count($definitions), Category::all());

        $event = Category::query()->where('name', 'Event Services')->first();
        $this->assertNotNull($event);
        $this->assertContains('Caterer', $event->subcategories ?? []);
        $this->assertContains('DJ', $event->subcategories ?? []);

        $tech = Category::query()->where('name', 'Tech Repair Services')->first();
        $this->assertNotNull($tech);
        $this->assertContains('Phone Repairer', $tech->subcategories ?? []);
    }

    public function test_featured_business_seeder_fails_without_category_seeder(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing category');

        $this->seed(FeaturedBusinessSeeder::class);
    }

    public function test_featured_business_seeder_uses_only_seeded_categories(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(FeaturedBusinessSeeder::class);

        $this->assertSame(8, BusinessInfo::query()->count());

        $categoryIds = Category::query()->pluck('id')->all();
        foreach (BusinessInfo::query()->cursor() as $business) {
            $this->assertContains($business->category_id, $categoryIds);
        }
    }
}
