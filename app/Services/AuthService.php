<?php

namespace App\Services;

use App\Enums\AdminStatus;
use App\Enums\UserStatus;
use App\Mail\ForgotPasswordOtpMail;
use App\Mail\OtpVerificationMail;
use App\Models\Admin;
use App\Models\AuthOtp;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const TWO_FACTOR_LOGIN_TTL_MINUTES = 5;

    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function register(array $validated): array
    {
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'status' => UserStatus::Pending->value,
            'wants_marketing_emails' => $validated['wants_marketing_emails'] ?? false,
            'password' => $validated['password'],
        ]);

        $otp = $this->issueOtp($user);
        $token = $this->issueAccessToken($user);

        $this->deliverOtpMail($user, $otp->code, false);

        return compact('user', 'otp', 'token');
    }

    public function verifyOtp(string $code): ?array
    {
        /** @var AuthOtp|null $otp */
        $otp = AuthOtp::query()
            ->where('code', hash('sha256', $code))
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp || $otp->expires_at->isPast()) {
            return null;
        }

        $subject = $otp->admin_id ? $otp->admin : $otp->user;

        if (! $subject instanceof User && ! $subject instanceof Admin) {
            return null;
        }

        $otp->update(['consumed_at' => now()]);

        if (! $subject->email_verified_at) {
            $payload = ['email_verified_at' => now()];

            if ($subject instanceof User) {
                $payload['status'] = UserStatus::Active->value;
            }

            if ($subject instanceof Admin) {
                $payload['status'] = AdminStatus::Active;
            }

            $subject->forceFill($payload)->save();
        }

        $token = $subject instanceof Admin
            ? $this->issueAdminAccessToken($subject)
            : $this->issueAccessToken($subject);

        return [
            'user' => $subject,
            'token' => $token,
        ];
    }

    public function resolveLoginUser(string $email, string $password): User
    {
        $user = User::query()
            ->where('email', Str::lower($email))
            ->first();

        if (! $user instanceof User) {
            throw new \Exception('No account found with this email address.');
        }

        if (! Hash::check($password, $user->password)) {
            throw new \Exception('Incorrect password.');
        }

        return $user;
    }

    public function initiateTwoFactorLogin(User $user, ?string $expectedRole = null): string
    {
        $token = Str::random(64);

        Cache::put($this->twoFactorLoginCacheKey($token), [
            'user_id' => $user->id,
            'expected_role' => $expectedRole,
        ], now()->addMinutes(self::TWO_FACTOR_LOGIN_TTL_MINUTES));

        return $token;
    }

    public function completeTwoFactorLogin(string $token, string $code): User
    {
        $cacheKey = $this->twoFactorLoginCacheKey($token);
        /** @var array{user_id: int, expected_role?: string|null}|null $payload */
        $payload = Cache::get($cacheKey);

        if (! is_array($payload) || ! isset($payload['user_id'])) {
            throw ValidationException::withMessages([
                'two_factor_token' => ['Your login session has expired. Please sign in again.'],
            ]);
        }

        $user = User::query()->find($payload['user_id']);
        if (! $user instanceof User) {
            Cache::forget($cacheKey);
            throw ValidationException::withMessages([
                'two_factor_token' => ['Your login session has expired. Please sign in again.'],
            ]);
        }

        if (! $this->twoFactor->isEnabled($user)) {
            Cache::forget($cacheKey);
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication is not enabled for this account.'],
            ]);
        }

        if (! $this->twoFactor->verify($user, $code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided two factor authentication code was invalid.'],
            ]);
        }

        $expectedRole = $payload['expected_role'] ?? null;
        if (is_string($expectedRole) && $expectedRole !== '' && $user->role !== $expectedRole) {
            throw ValidationException::withMessages([
                'code' => ["This account is registered as a {$user->role}. Please log in with the correct account type."],
            ]);
        }

        Cache::forget($cacheKey);

        return $user;
    }

    public function resolveAdminLoginUser(string $email, string $password): Admin
    {

        $admin = Admin::query()
            ->where('email', Str::lower($email))
            ->first();
        if (! $admin instanceof Admin) {
            throw new \Exception('No admin account found with this email address.');
        }

        if (! Hash::check($password, $admin->password)) {
            throw new \Exception('Incorrect password.');
        }

        return $admin;
    }

    public function forgotPassword(string $email, ?string $role = null): ?array
    {
        $subject = $this->resolvePasswordResetSubject($email, $role);

        if (! $subject instanceof User && ! $subject instanceof Admin) {
            return null;
        }

        $otp = $this->issueOtp($subject);
        $plainToken = Str::random(64);
        $passwordBroker = $subject instanceof Admin ? 'admins' : 'users';

        DB::table($this->passwordResetTable($passwordBroker))->updateOrInsert(
            ['email' => $subject->email],
            [
                'token' => hash('sha256', $plainToken),
                'created_at' => now(),
            ]
        );

        $this->deliverOtpMail($subject, $otp->code, true);

        return [
            'otp' => $otp,
            'token' => $plainToken,
        ];
    }

    public function verifyForgotPasswordOtp(string $email, string $code, string $token, ?string $role = null): bool
    {
        $subject = $this->resolvePasswordResetSubject($email, $role);

        if (
            (! $subject instanceof User && ! $subject instanceof Admin)
            || ! $this->isValidPasswordResetToken(
                $subject->email,
                $token,
                $subject instanceof Admin ? 'admins' : 'users',
            )
        ) {
            return false;
        }

        /** @var AuthOtp|null $otp */
        $otpQuery = AuthOtp::query()
            ->where('code', hash('sha256', $code))
            ->whereNull('consumed_at')
            ->latest();

        if ($subject instanceof Admin) {
            $otpQuery->where('admin_id', $subject->id);
        } else {
            $otpQuery->where('user_id', $subject->id);
        }

        $otp = $otpQuery->first();

        if (! $otp instanceof AuthOtp || $otp->expires_at->isPast()) {
            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }

    public function verifyForgotPasswordToken(string $email, string $token, ?string $role = null): bool
    {
        $subject = $this->resolvePasswordResetSubject($email, $role);

        if (! $subject instanceof User && ! $subject instanceof Admin) {
            return false;
        }

        return $this->isValidPasswordResetToken(
            $subject->email,
            $token,
            $subject instanceof Admin ? 'admins' : 'users',
        );
    }

    public function resetPasswordByEmail(string $email, string $password, string $token, ?string $role = null): bool
    {
        $subject = $this->resolvePasswordResetSubject($email, $role);
        $passwordBroker = $subject instanceof Admin ? 'admins' : 'users';

        if (
            (! $subject instanceof User && ! $subject instanceof Admin)
            || ! $this->isValidPasswordResetToken($subject->email, $token, $passwordBroker)
        ) {
            return false;
        }

        $subject->update(['password' => $password]);
        DB::table($this->passwordResetTable($passwordBroker))->where('email', $subject->email)->delete();

        return true;
    }

    public function resendForgotPasswordOtp(string $email, string $token, ?string $role = null): ?AuthOtp
    {
        $subject = $this->resolvePasswordResetSubject($email, $role);

        if (
            (! $subject instanceof User && ! $subject instanceof Admin)
            || ! $this->isValidPasswordResetToken(
                $subject->email,
                $token,
                $subject instanceof Admin ? 'admins' : 'users',
            )
        ) {
            return null;
        }

        $otp = $this->issueOtp($subject);

        $this->deliverOtpMail($subject, $otp->code, true);

        return $otp;
    }

    public function resendOtp(User|Admin $subject): AuthOtp
    {
        $otp = $this->issueOtp($subject);

        $this->deliverOtpMail($subject, $otp->code, false);

        return $otp;
    }

    public function issueAccessToken(User $user): string
    {
        return $user->createToken('Auth Token')->accessToken;
    }

    private function twoFactorLoginCacheKey(string $token): string
    {
        return 'two_factor_login:' . hash('sha256', $token);
    }

    public function issueAdminAccessToken(Admin $admin): string
    {
        return $admin->createToken('Admin Auth Token')->accessToken;
    }

    private function issueOtp(User|Admin $subject): AuthOtp
    {
        /** @var Builder<AuthOtp> $otpQuery */
        $otpQuery = AuthOtp::query();
        $otpQuery->whereNull('consumed_at');

        if ($subject instanceof Admin) {
            $otpQuery->where('admin_id', $subject->id);
        } else {
            $otpQuery->where('user_id', $subject->id);
        }

        $otpQuery->delete();

        $plainCode = (string) random_int(100000, 999999);

        return AuthOtp::query()->create([
            'user_id' => $subject instanceof User ? $subject->id : null,
            'admin_id' => $subject instanceof Admin ? $subject->id : null,
            'code' => hash('sha256', $plainCode),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ])->forceFill(['code' => $plainCode]);
    }

    private function isValidPasswordResetToken(string $email, string $token, string $passwordBroker = 'users'): bool
    {
        $email = Str::lower($email);
        $table = $this->passwordResetTable($passwordBroker);
        $tokenRow = DB::table($table)
            ->where('email', $email)
            ->first();

        if (! $tokenRow) {
            return false;
        }

        $expiresInMinutes = (int) Config::get("auth.passwords.{$passwordBroker}.expire", 60);
        $createdAt = isset($tokenRow->created_at) ? Carbon::parse($tokenRow->created_at) : null;

        if (! $createdAt instanceof Carbon || $createdAt->addMinutes($expiresInMinutes)->isPast()) {
            DB::table($table)->where('email', $email)->delete();

            return false;
        }

        return hash_equals((string) $tokenRow->token, hash('sha256', $token));
    }

    private function deliverOtpMail(User|Admin $subject, string $otpCode, bool $isForgotPasswordFlow): void
    {
        $mail = $isForgotPasswordFlow
            ? new ForgotPasswordOtpMail($otpCode, $subject->first_name)
            : new OtpVerificationMail($otpCode, $subject->first_name);

        $context = [
            'id' => $subject->id,
            'type' => $subject instanceof Admin ? 'admin' : 'user',
            'email' => $subject->email,
            'flow' => $isForgotPasswordFlow ? 'forgot-password' : 'verify-account',
        ];

        try {
            Mail::to($subject->email)->queue($mail);
            Log::info('OTP mail queued successfully.', $context);
        } catch (\Throwable $throwable) {
            Log::warning('OTP mail queue failed. Trying immediate send.', $context + [
                'error' => $throwable->getMessage(),
            ]);

            try {
                Mail::to($subject->email)->send($mail);
                Log::info('OTP mail sent immediately after queue failure.', $context);
            } catch (\Throwable $fallbackThrowable) {
                Log::error('OTP mail delivery failed.', $context + [
                    'error' => $fallbackThrowable->getMessage(),
                ]);

                throw $fallbackThrowable;
            }
        }
    }

    private function resolvePasswordResetSubject(string $email, ?string $role): User|Admin|null
    {
        $normalizedEmail = Str::lower($email);

        if ($role === 'admin') {
            return Admin::query()->where('email', $normalizedEmail)->first();
        }

        return User::query()->where('email', $normalizedEmail)->first();
    }

    private function passwordResetTable(string $passwordBroker): string
    {
        return (string) Config::get("auth.passwords.{$passwordBroker}.table", 'password_reset_tokens');
    }
}
