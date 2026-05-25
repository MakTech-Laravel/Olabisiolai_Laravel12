<?php

namespace Tests\Feature\Api\V1;

use App\Models\BusinessInfo;
use App\Models\BusinessProfileView;
use App\Models\User;
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
}
