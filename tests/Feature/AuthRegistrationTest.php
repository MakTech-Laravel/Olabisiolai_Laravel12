<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
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

    public function test_register_issues_otp_and_verification_returns_token(): void
    {
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'verification_channel' => 'email',
            'email' => 'newuser@example.com',
            'role' => 'user',
            'password' => 'Secret12!',
            'password_confirmation' => 'Secret12!',
            'accept_terms' => true,
        ]);

        $registerResponse->assertCreated();
        $registerResponse->assertJsonStructure([
            'data' => ['token', 'verification_status', 'otp', 'verification_channel'],
        ]);
        $registerResponse->assertJsonPath('data.verification_status', 'unverified');
        $registerResponse->assertJsonPath('data.verification_channel', 'email');
        $this->assertNotEmpty($registerResponse->json('data.token'));
        $this->assertSame('pending', User::where('email', 'newuser@example.com')->first()?->status->value);

        $verifyResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'code' => $registerResponse->json('data.otp'),
        ]);

        $verifyResponse->assertOk();
        $verifyResponse->assertJsonPath('data.verification_status', 'verified');
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);
        $this->assertNotNull(User::where('email', 'newuser@example.com')->value('email_verified_at'));
        $this->assertInstanceOf(User::class, User::where('email', 'newuser@example.com')->first());
    }

    public function test_register_validation_requires_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['first_name', 'last_name', 'verification_channel', 'role', 'password']);
    }

    public function test_marketplace_login_rejects_admin_user(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'AdminPass12!',
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'AdminPass12!',
            'portal' => 'marketplace',
        ]);

        $response->assertForbidden();
    }

    public function test_login_requires_email_verification_for_marketplace_and_admin_urls(): void
    {
        User::factory()->unverified()->create([
            'email' => 'user-unverified@example.com',
            'password' => 'UserPass12!',
            'role' => 'user',
        ]);

        User::factory()->unverified()->create([
            'email' => 'admin-unverified@example.com',
            'password' => 'AdminPass12!',
            'role' => 'admin',
        ]);

        $marketplaceResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'user-unverified@example.com',
            'password' => 'UserPass12!',
            'portal' => 'marketplace',
        ]);

        $marketplaceResponse->assertOk();
        $marketplaceResponse->assertJsonPath('data.verification_status', 'unverified');
        $marketplaceResponse->assertJsonStructure(['data' => ['token', 'otp']]);

        $adminResponse = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin-unverified@example.com',
            'password' => 'AdminPass12!',
        ]);

        $adminResponse->assertForbidden();
        $adminResponse->assertJsonPath('verification_status', 'unverified');
    }

    public function test_admin_login_only_allows_admins(): void
    {
        User::factory()->create([
            'email' => 'vendor@example.com',
            'password' => 'VendorPass12!',
            'role' => 'vendor',
        ]);

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'vendor@example.com',
            'password' => 'VendorPass12!',
        ]);

        $response->assertForbidden();
    }

    public function test_reset_password_uses_forgot_password_otp_flow(): void
    {
        User::factory()->create([
            'email' => 'reset@example.com',
            'phone' => '+2348099990000',
            'password' => 'OldPass12!',
            'role' => 'user',
        ]);

        $forgotResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $forgotResponse->assertOk();
        $forgotResponse->assertJsonStructure(['Otp', 'Token']);

        $verifyTokenResponse = $this->postJson('/api/v1/auth/forgot-password/verify-token', [
            'email' => 'reset@example.com',
            'token' => $forgotResponse->json('Token'),
        ]);

        $verifyTokenResponse->assertOk();

        $verifyOtpResponse = $this->postJson('/api/v1/auth/forgot-password/verify-otp', [
            'email' => 'reset@example.com',
            'code' => $forgotResponse->json('Otp'),
            'token' => $forgotResponse->json('Token'),
        ]);

        $verifyOtpResponse->assertOk();

        $resetResponse = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $forgotResponse->json('Token'),
            'password' => 'NewPass12!',
            'password_confirmation' => 'NewPass12!',
        ]);

        $resetResponse->assertOk();
    }

    public function test_forgot_password_returns_not_found_for_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertNotFound();
        $response->assertJsonPath('message', 'No account was found with this email address.');
    }
}
