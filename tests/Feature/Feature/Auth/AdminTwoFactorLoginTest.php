<?php

namespace Tests\Feature\Feature\Auth;

use App\Enums\AdminStatus;
use App\Models\Admin;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\ClientRepository;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminTwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $clientRepository = app(ClientRepository::class);
        $clientRepository->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );
        $clientRepository->createPersonalAccessGrantClient(
            'Testing Admin Personal Access Client',
            config('auth.guards.admin_api.provider'),
        );
    }

    public function test_admin_login_requires_two_factor_challenge_when_enabled(): void
    {
        $this->adminWithTwoFactor('admin@example.com');

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'Secret12!',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.two_factor_required', true);
        $response->assertJsonPath('data.verification_status', 'two_factor_required');
        $this->assertNotEmpty($response->json('data.two_factor_token'));
        $this->assertNull($response->json('data.token'));
    }

    public function test_legacy_admin_login_endpoint_requires_two_factor_challenge_when_enabled(): void
    {
        $this->adminWithTwoFactor('admin@example.com');

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'Secret12!',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('two_factor_required', true);
        $response->assertJsonPath('verification_status', 'two_factor_required');
        $this->assertNotEmpty($response->json('two_factor_token'));
        $this->assertNull($response->json('token'));
    }

    public function test_admin_two_factor_verify_issues_token_with_valid_code(): void
    {
        Mail::fake();

        $admin = $this->adminWithTwoFactor('admin@example.com');
        $secret = (string) $admin->two_factor_secret;

        $loginResponse = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'Secret12!',
        ]);

        Mail::assertQueued(\App\Mail\OtpVerificationMail::class);

        $challenge = $loginResponse->json('data.two_factor_token');
        $emailOtp = $loginResponse->json('data.otp');

        $google2fa = app(Google2FA::class);
        $code = $google2fa->getCurrentOtp($secret);

        $this->postJson('/api/v1/auth/admin/two-factor/verify', [
            'two_factor_token' => $challenge,
            'code' => $code,
        ])->assertOk();

        $loginResponse = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'Secret12!',
        ]);

        $challenge = $loginResponse->json('data.two_factor_token');
        $emailOtp = $loginResponse->json('data.otp');
        $this->assertNotEmpty($emailOtp);

        $this->postJson('/api/v1/auth/admin/two-factor/verify', [
            'two_factor_token' => $challenge,
            'code' => $emailOtp,
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_admin_two_factor_verify_rejects_invalid_code(): void
    {
        $this->adminWithTwoFactor('admin@example.com');

        $challenge = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'Secret12!',
        ])->json('data.two_factor_token');

        $response = $this->postJson('/api/v1/auth/admin/two-factor/verify', [
            'two_factor_token' => $challenge,
            'code' => '000000',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('success', false);
    }

    private function adminWithTwoFactor(string $email): Admin
    {
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey();

        $admin = Admin::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'name' => 'Test Admin',
            'email' => $email,
            'password' => 'Secret12!',
            'email_verified_at' => now(),
            'status' => AdminStatus::Active,
        ]);

        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['ABCD-EFGH'],
        ])->save();

        $this->assertTrue(app(TwoFactorAuthenticationService::class)->isEnabled($admin->fresh()));

        return $admin->fresh();
    }
}
