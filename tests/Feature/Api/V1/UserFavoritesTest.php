<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BusinessStatus;
use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UserFavoritesTest extends TestCase
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

    public function test_user_can_toggle_favorite_and_list(): void
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

        DB::table('reviews')->insert([
            'user_id' => null,
            'business_id' => $business->id,
            'full_name' => 'Test Reviewer',
            'is_anonymous' => false,
            'rating' => 5,
            'review_text' => 'Great service.',
            'is_approved' => true,
            'flag_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('reviews')->insert([
            'user_id' => null,
            'business_id' => $business->id,
            'full_name' => 'Test Reviewer',
            'is_anonymous' => false,
            'rating' => 4,
            'review_text' => 'Good service.',
            'is_approved' => true,
            'flag_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test')->accessToken;

        $toggleResponse = $this->withToken($token)->postJson('/api/v1/user/favorites/toggle', [
            'business_info_id' => $business->id,
        ]);
        $toggleResponse->assertCreated();
        $toggleResponse->assertJsonPath('data.favorited', true);
        $toggleResponse->assertJsonPath('data.business_info_id', $business->id);

        $listResponse = $this->withToken($token)->getJson('/api/v1/user/favorites');
        $listResponse->assertOk();
        $listResponse->assertJsonPath('data.favorites.0.business_info_id', $business->id);
        $listResponse->assertJsonPath('data.favorites.0.is_verified', true);
        $listResponse->assertJsonPath('data.favorites.0.reviews_count', 2);
        $this->assertSame(4.5, $listResponse->json('data.favorites.0.rating'));

        $removeResponse = $this->withToken($token)->postJson('/api/v1/user/favorites/toggle', [
            'business_info_id' => $business->id,
        ]);
        $removeResponse->assertOk();
        $removeResponse->assertJsonPath('data.favorited', false);
        $removeResponse->assertJsonPath('data.business_info_id', $business->id);

        $emptyResponse = $this->withToken($token)->getJson('/api/v1/user/favorites');
        $emptyResponse->assertOk();
        $this->assertSame([], $emptyResponse->json('data.favorites'));
    }

    public function test_user_can_favorite_active_unverified_business(): void
    {
        $category = Category::factory()->create(['name' => 'Plumbing']);
        $location = Location::factory()->create();

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $unverifiedBusiness = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'verification_status' => VerificationStatus::None,
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/user/favorites/toggle', [
            'business_info_id' => $unverifiedBusiness->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.favorited', true);
    }

    public function test_vendor_can_toggle_favorite(): void
    {
        $category = Category::factory()->create(['name' => 'Plumbing']);
        $location = Location::factory()->create();

        $listedVendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $business = BusinessInfo::factory()->create([
            'user_id' => $listedVendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $token = $vendor->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/user/favorites/toggle', [
            'business_info_id' => $business->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.favorited', true);
    }

    public function test_user_cannot_favorite_inactive_business(): void
    {
        $category = Category::factory()->create(['name' => 'Plumbing']);
        $location = Location::factory()->create();

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $inactiveBusiness = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Inactive,
            'is_flagged' => false,
        ]);

        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/user/favorites/toggle', [
            'business_info_id' => $inactiveBusiness->id,
        ]);

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
    }
}
