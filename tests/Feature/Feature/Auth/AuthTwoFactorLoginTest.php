<?php

namespace Tests\Feature\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\ClientRepository;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthTwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );

        Mail::fake();
    }

    public function test_login_requires_two_factor_challenge_when_enabled(): void
    {
        $user = $this->userWithTwoFactor('vendor@example.com');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'vendor@example.com',
            'password' => 'Secret12!',
            'role' => 'vendor',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.two_factor_required', true);
        $response->assertJsonPath('data.verification_status', 'two_factor_required');
        $this->assertNotEmpty($response->json('data.two_factor_token'));
        $this->assertNull($response->json('data.token'));
    }

    public function test_two_factor_verify_issues_token_with_valid_code(): void
    {
        $user = $this->userWithTwoFactor('vendor@example.com');
        $secret = (string) $user->two_factor_secret;

        $challenge = $this->postJson('/api/v1/auth/login', [
            'email' => 'vendor@example.com',
            'password' => 'Secret12!',
            'role' => 'vendor',
        ])->json('data.two_factor_token');

        $google2fa = app(Google2FA::class);
        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/api/v1/auth/two-factor/verify', [
            'two_factor_token' => $challenge,
            'code' => $code,
            'role' => 'vendor',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data.token'));
        $response->assertJsonPath('data.user.role', 'vendor');
    }

    public function test_two_factor_verify_rejects_invalid_code(): void
    {
        $this->userWithTwoFactor('vendor@example.com');

        $challenge = $this->postJson('/api/v1/auth/login', [
            'email' => 'vendor@example.com',
            'password' => 'Secret12!',
        ])->json('data.two_factor_token');

        $response = $this->postJson('/api/v1/auth/two-factor/verify', [
            'two_factor_token' => $challenge,
            'code' => '000000',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('success', false);
    }

    private function userWithTwoFactor(string $email): User
    {
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'email' => $email,
            'password' => 'Secret12!',
            'role' => 'vendor',
            'email_verified_at' => now(),
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['ABCD-EFGH'],
        ]);

        $this->assertTrue(app(TwoFactorAuthenticationService::class)->isEnabled($user->fresh()));

        return $user->fresh();
    }
}
