<?php

namespace Tests\Feature\Feature\Auth;

use App\Mail\ForgotPasswordOtpMail;
use App\Mail\OtpVerificationMail;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class AuthLoginTest extends TestCase
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

    // ---------------------------------------------------------------
    // Registration emails
    // ---------------------------------------------------------------

    public function test_register_queues_otp_verification_email(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'verification_channel' => 'email',
            'email' => 'jane@example.com',
            'role' => 'user',
            'password' => 'Secret12!',
            'password_confirmation' => 'Secret12!',
        ])->assertCreated();

        Mail::assertQueued(OtpVerificationMail::class, function (OtpVerificationMail $mail) {
            return $mail->hasTo('jane@example.com');
        });
    }

    // ---------------------------------------------------------------
    // Marketplace login (user & vendor)
    // ---------------------------------------------------------------

    public function test_verified_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'Secret12!',
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'Secret12!',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'token', 'verification_status', 'user']);
        $response->assertJsonPath('verification_status', 'verified');
        $response->assertJsonPath('user.role', 'user');
    }

    public function test_verified_vendor_can_login(): void
    {
        User::factory()->create([
            'email' => 'vendor@example.com',
            'password' => 'Secret12!',
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'vendor@example.com',
            'password' => 'Secret12!',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.role', 'vendor');
    }

    public function test_unverified_user_cannot_login(): void
    {
        User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
            'password' => 'Secret12!',
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unverified@example.com',
            'password' => 'Secret12!',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('verification_status', 'unverified');
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'Secret12!',
            'role' => 'user',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'WrongPass1!',
        ])->assertUnauthorized();
    }

    public function test_login_always_rejects_admin(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'Secret12!',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'Secret12!',
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Admin login
    // ---------------------------------------------------------------

    public function test_admin_can_login_via_admin_endpoint(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'Secret12!',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'Secret12!',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'token', 'user']);
        $response->assertJsonPath('user.role', 'admin');
    }

    public function test_admin_endpoint_rejects_user(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'Secret12!',
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'user@example.com',
            'password' => 'Secret12!',
        ])->assertForbidden();
    }

    public function test_admin_endpoint_rejects_vendor(): void
    {
        User::factory()->create([
            'email' => 'vendor@example.com',
            'password' => 'Secret12!',
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'vendor@example.com',
            'password' => 'Secret12!',
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------
    // OTP resend
    // ---------------------------------------------------------------

    public function test_authenticated_unverified_user_can_resend_otp(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/otp/resend');

        $response->assertOk();
        $response->assertJsonPath('verification_status', 'unverified');
        Mail::assertQueued(OtpVerificationMail::class, fn($m) => $m->hasTo($user->email));
    }

    public function test_resend_otp_returns_already_verified_for_verified_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/otp/resend');

        $response->assertOk();
        $response->assertJsonPath('verification_status', 'verified');
        Mail::assertNotQueued(OtpVerificationMail::class);
    }

    // ---------------------------------------------------------------
    // Logout
    // ---------------------------------------------------------------

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $response->assertJsonPath('role', 'user');
    }

    public function test_unauthenticated_logout_returns_401(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // Profile
    // ---------------------------------------------------------------

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/auth/profile');

        $response->assertOk();
        $response->assertJsonPath('data.email', $user->email);
        $response->assertJsonStructure([
            'data' => ['id', 'first_name', 'last_name', 'email', 'role', 'location', 'image_path', 'image_url'],
        ]);
    }

    public function test_unauthenticated_profile_returns_401(): void
    {
        $this->getJson('/api/v1/auth/profile')->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // Role-based route guards
    // ---------------------------------------------------------------

    public function test_admin_route_rejects_user_role(): void
    {
        $user = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $token = $user->createToken('test')->accessToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();
    }

    public function test_user_route_rejects_admin_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $token = $admin->createToken('test')->accessToken;

        $this->withToken($token)
            ->getJson('/api/v1/user/dashboard')
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Forgot-password email
    // ---------------------------------------------------------------

    public function test_forgot_password_queues_otp_email(): void
    {
        User::factory()->create([
            'email' => 'forgot@example.com',
            'role' => 'user',
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'forgot@example.com',
        ])->assertOk();

        Mail::assertQueued(ForgotPasswordOtpMail::class, fn($m) => $m->hasTo('forgot@example.com'));
    }

    // ---------------------------------------------------------------
    // Forgot-password OTP resend
    // ---------------------------------------------------------------

    public function test_resend_forgot_password_otp_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com', 'role' => 'user']);

        $result = app(AuthService::class)->forgotPassword('reset@example.com');

        Mail::assertQueued(ForgotPasswordOtpMail::class);
        Mail::fake();

        $this->postJson('/api/v1/auth/forgot-password/resend-otp', [
            'email' => 'reset@example.com',
            'token' => $result['token'],
        ])->assertOk()->assertJsonPath('message', 'A new OTP has been sent to your email address.');

        Mail::assertQueued(ForgotPasswordOtpMail::class, fn($m) => $m->hasTo('reset@example.com'));
    }

    public function test_resend_forgot_password_otp_with_invalid_token(): void
    {
        User::factory()->create(['email' => 'reset2@example.com']);

        $this->postJson('/api/v1/auth/forgot-password/resend-otp', [
            'email' => 'reset2@example.com',
            'token' => str_repeat('b', 64),
        ])->assertUnprocessable();
    }

    // ---------------------------------------------------------------
    // Token-only verify route
    // ---------------------------------------------------------------

    public function test_verify_token_route_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'tok@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password/verify-token', [
            'email' => 'tok@example.com',
            'token' => str_repeat('a', 64),
        ]);

        $response->assertUnprocessable();
    }
}
