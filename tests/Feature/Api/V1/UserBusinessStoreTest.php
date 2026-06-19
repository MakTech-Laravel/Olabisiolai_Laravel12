<?php

namespace Tests\Feature\Api\V1;

use App\Models\BusinessInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UserBusinessStoreTest extends TestCase
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

    public function test_creating_business_page_promotes_user_to_vendor_role(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/user/businesses');

        $response->assertCreated();
        $response->assertJsonPath('data.user.role', 'vendor');
        $response->assertJsonPath('data.created', true);

        $user->refresh();
        $this->assertSame('vendor', $user->role);
        $this->assertTrue(BusinessInfo::query()->where('user_id', $user->id)->exists());
    }
}
