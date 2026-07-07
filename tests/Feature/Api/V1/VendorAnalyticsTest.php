<?php

namespace Tests\Feature\Api\V1;

use App\Models\BusinessInfo;
use App\Models\BusinessProfileView;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class VendorAnalyticsTest extends TestCase
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

    public function test_vendor_without_business_gets_not_found(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $this->withToken($token)
            ->getJson('/api/v1/vendor/analytics')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.has_business', false);
    }

    public function test_vendor_can_load_analytics_payload(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $business = BusinessInfo::factory()->for($user)->create([
            'business_name' => 'Zenith Real Estate',
            'services_offered' => ['Luxury Staging', 'Interior Refit'],
        ]);

        BusinessProfileView::query()->create([
            'business_info_id' => $business->id,
            'viewed_at' => now(),
        ]);

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/vendor/analytics?range=30d');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_business', true)
            ->assertJsonPath('data.range', '30d')
            ->assertJsonStructure([
                'data' => [
                    'stats',
                    'traffic_trend',
                    'leads_by_channel',
                    'reach_areas',
                    'engagement_heatmap',
                    'top_listings',
                    'preview',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.stats.profile_views'));
        $this->assertCount(10, $response->json('data.traffic_trend.views_heights'));
    }

    public function test_followers_delta_compares_total_count_at_period_end(): void
    {
        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $business = BusinessInfo::factory()->for($vendor)->create();

        $followerA = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $followerB = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        $followedAt = now()->subDays(40);

        $followA = UserFollow::query()->create([
            'follower_id' => $followerA->id,
            'following_id' => $vendor->id,
            'business_info_id' => $business->id,
        ]);
        $followA->forceFill(['created_at' => $followedAt, 'updated_at' => $followedAt])->saveQuietly();

        $followB = UserFollow::query()->create([
            'follower_id' => $followerB->id,
            'following_id' => $vendor->id,
            'business_info_id' => $business->id,
        ]);
        $followB->forceFill(['created_at' => $followedAt, 'updated_at' => $followedAt])->saveQuietly();

        $token = $vendor->createToken('test')->accessToken;

        $beforeUnfollow = $this->withToken($token)->getJson('/api/v1/vendor/analytics?range=30d');
        $beforeUnfollow->assertOk();
        $beforeUnfollow->assertJsonPath('data.stats.followers_count', 2);
        $beforeUnfollow->assertJsonPath('data.stats.followers_delta_percent', 0);

        UserFollow::query()
            ->where('follower_id', $followerB->id)
            ->where('business_info_id', $business->id)
            ->first()
            ?->delete();

        $afterUnfollow = $this->withToken($token)->getJson('/api/v1/vendor/analytics?range=30d');
        $afterUnfollow->assertOk();
        $afterUnfollow->assertJsonPath('data.stats.followers_count', 1);
        $afterUnfollow->assertJsonPath('data.stats.followers_delta_percent', -50);
    }

    public function test_analytics_does_not_divide_by_zero_when_metrics_are_zero(): void
    {
        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        BusinessInfo::factory()->for($vendor)->create();

        $token = $vendor->createToken('test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/vendor/analytics?range=30d');

        $response->assertOk();
        $response->assertJsonPath('data.stats.total_enquiries', 0);
        $response->assertJsonPath('data.stats.total_enquiries_delta_percent', null);
        $response->assertJsonPath('data.stats.profile_views', 0);
        $response->assertJsonPath('data.stats.profile_views_delta_percent', null);
        $response->assertJsonPath('data.stats.messages_count', 0);
        $response->assertJsonPath('data.stats.messages_delta_percent', null);
        $response->assertJsonPath('data.stats.followers_count', 0);
        $response->assertJsonPath('data.stats.followers_delta_percent', null);
    }

    public function test_analytics_with_scoped_business_without_location_returns_ok(): void
    {
        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $business = BusinessInfo::factory()->for($vendor)->create([
            'location_id' => null,
        ]);

        $token = $vendor->createToken('test')->accessToken;

        $this->withToken($token)
            ->getJson('/api/v1/vendor/analytics?range=30d&business_id='.$business->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reach_areas', []);
    }

    public function test_followers_delta_shows_loss_when_all_unfollows_within_period(): void
    {
        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $business = BusinessInfo::factory()->for($vendor)->create();

        $followerA = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $followerB = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        $followA = UserFollow::query()->create([
            'follower_id' => $followerA->id,
            'following_id' => $vendor->id,
            'business_info_id' => $business->id,
        ]);
        $followA->delete();

        $followB = UserFollow::query()->create([
            'follower_id' => $followerB->id,
            'following_id' => $vendor->id,
            'business_info_id' => $business->id,
        ]);
        $followB->delete();

        $token = $vendor->createToken('test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/vendor/analytics?range=30d');

        $response->assertOk();
        $response->assertJsonPath('data.stats.followers_count', 0);
        $response->assertJsonPath('data.stats.followers_delta_percent', -100);
    }
}
