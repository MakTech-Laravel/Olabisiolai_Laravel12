<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCategoryShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_category_show_returns_category_without_authentication(): void
    {
        $category = Category::factory()->create([
            'name' => 'Test Category',
            'subcategories' => ['Alpha', 'Beta'],
        ]);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.category.id', $category->id);
        $response->assertJsonPath('data.category.name', 'Test Category');
        $response->assertJsonPath('data.category.subcategories', ['Alpha', 'Beta']);
        $response->assertJsonPath('data.category.subcategories_count', 2);
    }

    public function test_public_category_show_returns_not_found_for_unknown_id(): void
    {
        $response = $this->getJson('/api/v1/categories/999999');

        $response->assertNotFound();
    }
}
