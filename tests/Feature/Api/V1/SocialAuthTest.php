<?php

namespace Tests\Feature\Api\V1;

use App\Data\SocialAuth\SocialAuthProfile;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialAuth\SocialAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class SocialAuthTest extends TestCase
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

    public function test_social_providers_endpoint_lists_google(): void
    {
        $response = $this->getJson('/api/v1/auth/social/providers');

        $response->assertOk();
        $response->assertJsonPath('data.providers.0.provider', 'google');
        $response->assertJsonPath('data.providers.0.label', 'Google');
    }

    public function test_social_login_requires_credentials_payload(): void
    {
        $response = $this->postJson('/api/v1/auth/social/google/login', [
            'role' => 'user',
        ]);

        $response->assertUnprocessable();
    }

    public function test_social_login_with_google_id_token_creates_user_and_returns_token(): void
    {
        config([
            'services.google.client_id' => 'test-google-client-id',
        ]);

        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-123',
                'email' => 'social.user@example.com',
                'email_verified' => 'true',
                'name' => 'Social User',
                'given_name' => 'Social',
                'family_name' => 'User',
                'aud' => 'test-google-client-id',
            ]),
        ]);

        $response = $this->postJson('/api/v1/auth/social/google/login', [
            'role' => 'user',
            'id_token' => 'fake-google-id-token',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.verification_status', 'verified');
        $response->assertJsonPath('data.is_new_user', true);
        $response->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', [
            'email' => 'social.user@example.com',
            'role' => 'user',
        ]);

        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
            'provider_email' => 'social.user@example.com',
        ]);
    }

    public function test_social_login_links_existing_user_by_email(): void
    {
        config([
            'services.google.client_id' => 'test-google-client-id',
        ]);

        $user = User::factory()->create([
            'email' => 'existing.user@example.com',
            'role' => 'user',
        ]);

        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-999',
                'email' => 'existing.user@example.com',
                'email_verified' => 'true',
                'name' => 'Existing User',
                'aud' => 'test-google-client-id',
            ]),
        ]);

        $response = $this->postJson('/api/v1/auth/social/google/login', [
            'role' => 'user',
            'id_token' => 'fake-google-id-token',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_new_user', false);
        $response->assertJsonPath('data.user.email', 'existing.user@example.com');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-user-999',
        ]);
    }

    public function test_social_auth_service_reuses_existing_social_account(): void
    {
        $user = User::factory()->create([
            'email' => 'linked.user@example.com',
            'role' => 'vendor',
        ]);

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-linked-1',
            'provider_email' => 'linked.user@example.com',
        ]);

        $service = app(SocialAuthService::class);
        $result = $service->loginOrRegister(
            new SocialAuthProfile(
                provider: 'google',
                providerUserId: 'google-linked-1',
                email: 'linked.user@example.com',
                name: 'Linked User',
                firstName: 'Linked',
                lastName: 'User',
                avatarUrl: null,
                emailVerified: true,
            ),
            'vendor',
        );

        $this->assertFalse($result['is_new_user']);
        $this->assertSame($user->id, $result['user']->id);
        $this->assertNotEmpty($result['token']);
    }
}
