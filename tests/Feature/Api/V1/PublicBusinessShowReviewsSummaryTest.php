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

class PublicBusinessShowReviewsSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_show_includes_review_aggregates_for_approved_reviews_only(): void
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
        ]);

        Review::factory()->forBusiness($business)->create([
            'rating' => 5,
            'is_approved' => true,
        ]);
        Review::factory()->forBusiness($business)->create([
            'rating' => 4,
            'is_approved' => true,
        ]);
        Review::factory()->forBusiness($business)->notApproved()->create([
            'rating' => 1,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$business->id}");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEqualsWithDelta(4.5, (float) $response->json('data.business.average_rating'), 0.01);
        $response->assertJsonPath('data.business.reviews_count', 2);
        $response->assertJsonPath('data.reviews_summary.total_reviews', 2);
        $response->assertJsonPath('data.reviews_summary.average_rating', 4.5);
        $response->assertJsonPath('data.reviews_summary.rating_distribution.0.stars', 5);
        $response->assertJsonPath('data.reviews_summary.rating_distribution.0.count', 1);
        $response->assertJsonPath('data.reviews_summary.rating_distribution.1.stars', 4);
        $response->assertJsonPath('data.reviews_summary.rating_distribution.1.count', 1);
        $response->assertJsonPath('data.reviews_summary.rating_distribution.4.stars', 1);
        $response->assertJsonPath('data.reviews_summary.rating_distribution.4.count', 0);
    }
}
