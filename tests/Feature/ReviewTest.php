<?php

namespace Tests\Feature;

use App\Models\BusinessInfo;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * PUBLIC API TESTS
     */
    public function test_guest_cannot_submit_review(): void
    {
        $business = BusinessInfo::factory()->create();

        $response = $this->postJson('/api/v1/reviews/store', [
            'business_id' => $business->id,
            'full_name' => 'John Doe',
            'is_anonymous' => false,
            'rating' => 5,
            'review_text' => 'Excellent service! Highly recommended.',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_submit_review_with_user_id(): void
    {
        $business = BusinessInfo::factory()->create();
        $user = User::factory()->create();

        $files = [
            UploadedFile::fake()->image('review1.jpg'),
            UploadedFile::fake()->image('review2.jpg'),
        ];

        $response = $this->actingAs($user, 'api')
            ->post('/api/v1/reviews/store', [
                'business_id' => $business->id,
                'full_name' => 'Jane Smith',
                'is_anonymous' => false,
                'rating' => 4,
                'review_text' => 'Great experience, very professional.',
                'images' => $files,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'business_id' => $business->id,
            'user_id' => $user->id,
            'is_anonymous' => false,
            'rating' => 4,
        ]);
    }

    public function test_public_can_list_approved_reviews_by_business(): void
    {
        $business1 = BusinessInfo::factory()->create();
        $business2 = BusinessInfo::factory()->create();

        Review::factory()->count(3)->forBusiness($business1)->create(['is_approved' => true]);
        Review::factory()->count(2)->forBusiness($business2)->create(['is_approved' => true]);
        Review::factory()->forBusiness($business1)->create(['is_approved' => false]);

        $response = $this->getJson('/api/v1/reviews?business_id=' . $business1->id);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'pagination']);

        $this->assertEquals(3, count($response->json('data')));
    }

    public function test_review_validation_requires_business_id(): void
    {
        $response = $this->postJson('/api/v1/reviews', [
            'full_name' => 'John Doe',
            'rating' => 5,
            'review_text' => 'Good service',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['business_id']);
    }

    public function test_review_validation_enforces_rating_range(): void
    {
        $business = BusinessInfo::factory()->create();

        $response = $this->postJson('/api/v1/reviews', [
            'business_id' => $business->id,
            'full_name' => 'John Doe',
            'rating' => 6,
            'review_text' => 'Good service',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_review_accepts_single_optional_image(): void
    {
        $business = BusinessInfo::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->post('/api/v1/reviews/store', [
                'business_id' => $business->id,
                'full_name' => 'John Doe',
                'is_anonymous' => false,
                'rating' => 5,
                'review_text' => 'Good service overall.',
                'images' => [UploadedFile::fake()->image('single.jpg')],
            ]);

        $response->assertStatus(201)
            ->assertJsonCount(1, 'data.images');
    }

    public function test_unauthenticated_user_cannot_submit_review(): void
    {
        $business = BusinessInfo::factory()->create();

        $response = $this->postJson('/api/v1/reviews/store', [
            'business_id' => $business->id,
            'full_name' => 'John Doe',
            'is_anonymous' => false,
            'rating' => 5,
            'review_text' => 'Good service overall.',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * ADMIN API TESTS
     */
    public function test_admin_can_list_all_reviews(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $business = BusinessInfo::factory()->create();

        Review::factory()->count(5)->forBusiness($business)->create();

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/reviews');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'pagination']);

        $this->assertEquals(5, count($response->json('data')));
    }

    public function test_admin_can_view_specific_review(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $business = BusinessInfo::factory()->create();
        $review = Review::factory()->forBusiness($business)->create();

        $response = $this->actingAs($admin, 'api')
            ->postJson("/api/v1/admin/reviews/{$review->id}/view");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        $this->assertEquals($review->id, $response->json('data.id'));
    }

    public function test_admin_can_approve_review(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $business = BusinessInfo::factory()->create();
        $review = Review::factory()->forBusiness($business)->create(['is_approved' => false]);

        $response = $this->actingAs($admin, 'api')
            ->postJson("/api/v1/admin/reviews/{$review->id}/update", [
                'is_approved' => true,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_approved' => true,
            'flag_reason' => null,
        ]);
    }

    public function test_admin_can_flag_review(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $business = BusinessInfo::factory()->create();
        $review = Review::factory()->forBusiness($business)->create();

        $response = $this->actingAs($admin, 'api')
            ->postJson("/api/v1/admin/reviews/{$review->id}/update", [
                'is_approved' => false,
                'flag_reason' => 'Inappropriate content',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_approved' => false,
            'flag_reason' => 'Inappropriate content',
        ]);
        $this->assertNotNull($review->fresh()->flagged_at);
    }

    public function test_admin_can_delete_review(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $business = BusinessInfo::factory()->create();
        $review = Review::factory()->forBusiness($business)->create();

        $response = $this->actingAs($admin, 'api')
            ->postJson("/api/v1/admin/reviews/{$review->id}/delete");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_admin_can_bulk_approve_reviews(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $business = BusinessInfo::factory()->create();

        $reviews = Review::factory()->count(3)->forBusiness($business)->create(['is_approved' => false]);
        $reviewIds = $reviews->pluck('id')->toArray();

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/reviews/bulk-approve', [
                'review_ids' => $reviewIds,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message']);

        foreach ($reviewIds as $id) {
            $this->assertDatabaseHas('reviews', [
                'id' => $id,
                'is_approved' => true,
                'flag_reason' => null,
            ]);
        }
    }

    public function test_admin_can_bulk_flag_reviews(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $business = BusinessInfo::factory()->create();

        $reviews = Review::factory()->count(2)->forBusiness($business)->create();
        $reviewIds = $reviews->pluck('id')->toArray();

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/reviews/bulk-flag', [
                'review_ids' => $reviewIds,
                'flag_reason' => 'Spam content',
            ]);

        $response->assertStatus(200);

        foreach ($reviewIds as $id) {
            $this->assertDatabaseHas('reviews', [
                'id' => $id,
                'is_approved' => false,
                'flag_reason' => 'Spam content',
            ]);
        }
    }

    public function test_admin_can_get_review_statistics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $business = BusinessInfo::factory()->create();

        Review::factory()->count(5)->forBusiness($business)->create(['rating' => 5, 'is_approved' => true]);
        Review::factory()->count(3)->forBusiness($business)->create(['rating' => 4, 'is_approved' => true]);
        Review::factory()->count(2)->forBusiness($business)->create(['rating' => 2, 'is_approved' => false]);

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/reviews/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_reviews',
                    'approved_reviews',
                    'flagged_reviews',
                    'average_rating',
                    'rating_distribution',
                ],
            ]);

        $this->assertEquals(10, $response->json('data.total_reviews'));
        $this->assertEquals(2, $response->json('data.flagged_reviews'));
    }

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/admin/reviews');

        $response->assertStatus(403);
    }
}
