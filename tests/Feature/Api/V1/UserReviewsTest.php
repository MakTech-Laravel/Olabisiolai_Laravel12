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
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UserReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );
    }

    public function test_user_can_list_own_reviews(): void
    {
        $category = Category::factory()->create(['name' => 'Plumbing']);
        $location = Location::factory()->create([
            'state_name' => 'Lagos',
            'city_name' => 'Ikeja',
            'lga_name' => 'Ikeja',
        ]);

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $otherUser = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $mine = Review::factory()
            ->forUser($user)
            ->forBusiness($business)
            ->create([
                'full_name' => $user->name ?? 'Test User',
                'rating' => 5,
                'review_text' => 'Excellent work.',
            ]);

        Review::factory()
            ->forUser($otherUser)
            ->forBusiness($business)
            ->create([
                'rating' => 3,
                'review_text' => 'Someone else review.',
            ]);

        Review::factory()
            ->anonymous()
            ->forBusiness($business)
            ->create([
                'rating' => 4,
                'review_text' => 'Anonymous review.',
            ]);

        $token = $user->createToken('test')->accessToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user/reviews');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.reviews.0.id', $mine->id)
            ->assertJsonPath('data.reviews.0.rating', 5)
            ->assertJsonPath('data.reviews.0.business.id', $business->id);
    }

    public function test_vendor_cannot_access_user_reviews_endpoint(): void
    {
        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $token = $vendor->createToken('test')->accessToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user/reviews')
            ->assertForbidden();
    }
}
