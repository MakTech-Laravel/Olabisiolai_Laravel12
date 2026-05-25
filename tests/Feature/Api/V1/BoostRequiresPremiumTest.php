<?php

namespace Tests\Feature\Api\V1;

use App\Models\BusinessInfo;
use App\Models\User;
use Database\Seeders\PricingPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class BoostRequiresPremiumTest extends TestCase
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

    public function test_free_vendor_cannot_activate_boost(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        BusinessInfo::factory()->for($user)->create();

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/vendor/business/boost-status', [
            'is_active' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_premium_vendor_can_activate_boost(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        BusinessInfo::factory()->for($user)->premiumActive()->create();

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/vendor/business/boost-status', [
            'is_active' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.boost.is_active', true);
    }
}
