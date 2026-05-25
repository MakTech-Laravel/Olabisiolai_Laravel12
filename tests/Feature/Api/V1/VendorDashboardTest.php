<?php

namespace Tests\Feature\Api\V1;

use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\BusinessProfileView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class VendorDashboardTest extends TestCase
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
            ->getJson('/api/v1/vendor/dashboard')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.has_business', false);
    }

    public function test_vendor_can_load_dashboard_metrics(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $business = BusinessInfo::factory()->for($user)->create([
            'business_name' => 'Zenith Real Estate',
            'verification_status' => VerificationStatus::Pending,
        ]);

        BusinessProfileView::query()->create([
            'business_info_id' => $business->id,
            'viewed_at' => now(),
        ]);

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/vendor/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_business', true)
            ->assertJsonPath('data.business.name', 'Zenith Real Estate')
            ->assertJsonPath('data.verification.status', 'pending')
            ->assertJsonStructure([
                'data' => [
                    'business',
                    'subscription',
                    'verification',
                    'boost',
                    'stats',
                    'interactions',
                    'weekly_engagement',
                    'profile_completion',
                    'checklist',
                    'recent_activity',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.stats.profile_views'));
    }
}
