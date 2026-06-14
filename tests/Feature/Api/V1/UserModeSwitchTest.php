<?php

namespace Tests\Feature\Api\V1;

use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UserModeSwitchTest extends TestCase
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

    public function test_customer_can_switch_to_vendor_and_gets_free_business_template(): void
    {
        Category::factory()->create();
        Location::factory()->create();

        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/user/mode/vendor');

        $response->assertOk();
        $response->assertJsonPath('data.mode', 'vendor');
        $response->assertJsonPath('data.created_business', true);
        $response->assertJsonPath('data.user.role', 'vendor');
        $response->assertJsonPath('data.user.settings.active_profile_mode', 'vendor');

        $user->refresh();
        $this->assertSame('vendor', $user->role);
        $this->assertTrue(BusinessInfo::query()->where('user_id', $user->id)->exists());
    }

    public function test_vendor_with_existing_business_can_switch_to_customer(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
            'settings' => ['active_profile_mode' => 'vendor'],
        ]);
        BusinessInfo::factory()->for($user)->create();

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/user/mode/customer');

        $response->assertOk();
        $response->assertJsonPath('data.mode', 'customer');
        $response->assertJsonPath('data.user.role', 'user');
        $response->assertJsonPath('data.user.settings.active_profile_mode', 'customer');
        $this->assertNotNull($response->json('data.business_id'));

        $user->refresh();
        $this->assertSame('user', $user->role);
        $this->assertTrue(BusinessInfo::query()->where('user_id', $user->id)->exists());
    }

    public function test_switch_to_vendor_reuses_existing_business(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
        $business = BusinessInfo::factory()->for($user)->create();
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/user/mode/vendor');

        $response->assertOk();
        $response->assertJsonPath('data.created_business', false);
        $response->assertJsonPath('data.business_id', $business->id);
        $this->assertSame(1, BusinessInfo::query()->where('user_id', $user->id)->count());
    }
}
