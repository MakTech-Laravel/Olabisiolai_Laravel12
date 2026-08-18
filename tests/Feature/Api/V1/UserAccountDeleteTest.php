<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UserAccountDeleteTest extends TestCase
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

    public function test_wrong_password_returns_422(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'password' => 'password',
        ]);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->deleteJson('/api/v1/user/account', [
            'password' => 'not-the-password',
            'confirmation' => 'DELETE',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('success', false);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_success_deletes_user_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'password' => 'password',
        ]);
        $userId = $user->id;
        $token = $user->createToken('test')->accessToken;

        $this->assertDatabaseHas('oauth_access_tokens', ['user_id' => (string) $userId]);

        $response = $this->withToken($token)->deleteJson('/api/v1/user/account', [
            'password' => 'password',
            'confirmation' => 'DELETE',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseMissing('oauth_access_tokens', ['user_id' => (string) $userId]);
    }

    public function test_second_request_after_delete_returns_401(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'password' => 'password',
        ]);
        $token = $user->createToken('test')->accessToken;

        $this->withToken($token)->deleteJson('/api/v1/user/account', [
            'password' => 'password',
            'confirmation' => 'DELETE',
        ])->assertOk();

        Auth::forgetGuards();

        $this->withToken($token)->deleteJson('/api/v1/user/account', [
            'password' => 'password',
            'confirmation' => 'DELETE',
        ])->assertUnauthorized();
    }
}
