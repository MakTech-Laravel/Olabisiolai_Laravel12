<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BusinessStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UserFollowTest extends TestCase
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

    public function test_customer_can_follow_vendor_and_list_following(): void
    {
        [$vendor, $business] = $this->createVendorBusiness();
        $customer = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $token = $customer->createToken('test')->accessToken;

        $toggleResponse = $this->withToken($token)->postJson('/api/v1/user/follows/toggle', [
            'following_user_id' => $vendor->id,
            'business_id' => $business->id,
        ]);

        $toggleResponse->assertCreated();
        $toggleResponse->assertJsonPath('data.following', true);
        $toggleResponse->assertJsonPath('data.followers_count', 1);

        $statsResponse = $this->withToken($token)->getJson('/api/v1/user/follows/stats?business_id='.$business->id);
        $statsResponse->assertOk();
        $statsResponse->assertJsonPath('data.followers_count', 1);

        $followingResponse = $this->withToken($token)->getJson('/api/v1/user/follows/following');
        $followingResponse->assertOk();
        $followingResponse->assertJsonPath('data.following.0.following_user_id', $vendor->id);
        $followingResponse->assertJsonPath('data.following.0.business.id', $business->id);

        $publicResponse = $this->withToken($token)->getJson('/api/v1/businesses/'.$business->id);
        $publicResponse->assertOk();
        $publicResponse->assertJsonPath('data.business.followers_count', 1);
        $publicResponse->assertJsonPath('data.business.is_following', true);

        $unfollowResponse = $this->withToken($token)->postJson('/api/v1/user/follows/toggle', [
            'following_user_id' => $vendor->id,
            'business_id' => $business->id,
        ]);
        $unfollowResponse->assertOk();
        $unfollowResponse->assertJsonPath('data.following', false);

        $refollowResponse = $this->withToken($token)->postJson('/api/v1/user/follows/toggle', [
            'following_user_id' => $vendor->id,
            'business_id' => $business->id,
        ]);
        $refollowResponse->assertCreated();
        $refollowResponse->assertJsonPath('data.following', true);
        $refollowResponse->assertJsonPath('data.followers_count', 1);
    }

    public function test_followers_are_isolated_per_business_page(): void
    {
        [$vendor, $firstBusiness] = $this->createVendorBusiness('First Business');
        $secondBusiness = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $firstBusiness->category_id,
            'location_id' => $firstBusiness->location_id,
            'business_name' => 'Second Business',
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        $customer = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $token = $customer->createToken('test')->accessToken;

        $this->withToken($token)->postJson('/api/v1/user/follows/toggle', [
            'following_user_id' => $vendor->id,
            'business_id' => $firstBusiness->id,
        ])->assertCreated();

        $firstPublic = $this->withToken($token)->getJson('/api/v1/businesses/'.$firstBusiness->id);
        $firstPublic->assertOk();
        $firstPublic->assertJsonPath('data.business.followers_count', 1);

        $secondPublic = $this->withToken($token)->getJson('/api/v1/businesses/'.$secondBusiness->id);
        $secondPublic->assertOk();
        $secondPublic->assertJsonPath('data.business.followers_count', 0);
        $secondPublic->assertJsonPath('data.business.is_following', false);
    }

    public function test_vendor_can_follow_another_vendor(): void
    {
        [$vendorA, $businessA] = $this->createVendorBusiness();
        [$vendorB, $businessB] = $this->createVendorBusiness();

        $token = $vendorA->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/user/follows/toggle', [
            'following_user_id' => $vendorB->id,
            'business_id' => $businessB->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.following', true);
    }

    public function test_vendor_cannot_follow_customer(): void
    {
        [$vendor, $business] = $this->createVendorBusiness();
        $customer = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $token = $vendor->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/user/follows/toggle', [
            'following_user_id' => $customer->id,
            'business_id' => $business->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_user_cannot_follow_self(): void
    {
        [$vendor, $business] = $this->createVendorBusiness();
        $token = $vendor->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/user/follows/toggle', [
            'following_user_id' => $vendor->id,
            'business_id' => $business->id,
        ]);

        $response->assertUnprocessable();
    }

    /**
     * @return array{0: User, 1: BusinessInfo}
     */
    private function createVendorBusiness(string $name = 'Test Business'): array
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => $name,
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        return [$vendor, $business];
    }
}
