<?php

namespace Tests\Feature\Api\V1;

use App\Models\BusinessInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class VendorOnboardingStatusTest extends TestCase
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

    public function test_vendor_without_business_is_sent_to_onboarding(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/vendor/onboarding/status');

        $response->assertOk();
        $response->assertJsonPath('data.has_business', false);
        $response->assertJsonPath('data.can_access_onboarding', true);
        $response->assertJsonPath('data.redirect_to', '/vendor/choose-your-plan');
    }

    public function test_vendor_with_free_business_goes_to_dashboard_and_can_pay_premium(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        BusinessInfo::factory()->for($user)->create();

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/vendor/onboarding/status');

        $response->assertOk();
        $response->assertJsonPath('data.has_business', true);
        $response->assertJsonPath('data.can_access_onboarding', false);
        $response->assertJsonPath('data.redirect_to', '/vendor/dashboard');
        $response->assertJsonPath('data.subscription.can_pay_premium', true);
        $response->assertJsonPath('data.subscription.is_premium_active', false);
    }

    public function test_vendor_with_active_premium_cannot_pay_again(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        BusinessInfo::factory()->for($user)->premiumActive()->create();

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/vendor/onboarding/status');

        $response->assertOk();
        $response->assertJsonPath('data.redirect_to', '/vendor/dashboard');
        $response->assertJsonPath('data.subscription.is_premium_active', true);
        $response->assertJsonPath('data.subscription.can_pay_premium', false);
    }
}
