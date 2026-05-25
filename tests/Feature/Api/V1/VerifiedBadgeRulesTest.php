<?php

namespace Tests\Feature\Api\V1;

use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\User;
use Database\Seeders\PricingPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class VerifiedBadgeRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PricingPackageSeeder::class);

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );
    }

    public function test_premium_without_verification_has_no_badge(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        BusinessInfo::factory()->for($user)->premiumActive()->create([
            'verification_status' => VerificationStatus::None,
        ]);

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/vendor/business/show');

        $response->assertOk();
        $response->assertJsonPath('data.business.is_premium_active', true);
        $response->assertJsonPath('data.business.shows_verified_badge', false);
        $response->assertJsonPath('data.business.is_verified', false);
    }

    public function test_free_verified_business_has_badge_without_premium(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        BusinessInfo::factory()->for($user)->create([
            'verification_status' => VerificationStatus::Approved,
            'verified_at' => now(),
        ]);

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/vendor/business/show');

        $response->assertOk();
        $response->assertJsonPath('data.business.is_premium_active', false);
        $response->assertJsonPath('data.business.shows_verified_badge', true);
        $response->assertJsonPath('data.business.is_verified', true);
    }
}
