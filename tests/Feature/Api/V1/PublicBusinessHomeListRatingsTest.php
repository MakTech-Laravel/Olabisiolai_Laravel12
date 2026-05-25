<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BusinessStatus;
use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBusinessHomeListRatingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_list_includes_average_rating_and_reviews_count_for_approved_reviews(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
            'sort_order' => 100,
        ]);

        Review::factory()->forBusiness($business)->create(['rating' => 5, 'is_approved' => true]);
        Review::factory()->forBusiness($business)->create(['rating' => 3, 'is_approved' => true]);
        Review::factory()->forBusiness($business)->notApproved()->create(['rating' => 1]);

        $response = $this->getJson('/api/v1/businesses/home?per_page=12');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.businesses.0.id', $business->id);
        $this->assertEqualsWithDelta(4.0, (float) $response->json('data.businesses.0.average_rating'), 0.01);
        $response->assertJsonPath('data.businesses.0.reviews_count', 2);
    }
}
