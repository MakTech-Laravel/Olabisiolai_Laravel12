<?php

namespace App\Services;

use App\Enums\AdminStatus;
use App\Enums\OtpPurpose;
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
use App\Support\PhoneNormalizer;

class AuthService
{
    private const TWO_FACTOR_LOGIN_TTL_MINUTES = 5;

    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
        private readonly TermiiService $termii,
    ) {}

    public function register(array $validated): array
    {
        $channel = (string) $validated['verification_channel'];
        $email = filled($validated['email'] ?? null) ? Str::lower((string) $validated['email']) : null;
        $phone = filled($validated['phone'] ?? null)
            ? (PhoneNormalizer::normalize((string) $validated['phone']) ?? (string) $validated['phone'])
            : null;

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'email' => $email,
            'phone' => $phone,
            'role' => $validated['role'],
            'status' => UserStatus::Pending->value,
            'wants_marketing_emails' => $validated['wants_marketing_emails'] ?? false,
            'password' => $validated['password'],
            'settings' => [
                'registration_verification_channel' => $channel,
            ],
        ]);

        $otp = $this->issueOtp($user, OtpPurpose::Register);
        $token = $this->issueAccessToken($user);

        $this->deliverRegistrationOtp($user, $otp->code, $channel);

        return [
            'user' => $user,
            'otp' => $otp,
            'token' => $token,
            'verification_channel' => $channel,
        ];
    }

    public function verifyOtp(string $code, ?string $phone = null, ?User $authenticatedUser = null): ?array
    {
        $query = AuthOtp::query()
            ->where('code', hash('sha256', $code))
            ->whereNull('consumed_at')
            ->latest();

        if ($authenticatedUser instanceof User) {
            $query->where('user_id', $authenticatedUser->id)
                ->where('purpose', OtpPurpose::Register->value);
        } elseif ($phone !== null) {
            $user = $this->resolveUserByPhone($phone);
            if (! $user instanceof User) {
                return null;
            }
            $query->where('user_id', $user->id)
                ->where('purpose', OtpPurpose::Register->value);
        }

        /** @var AuthOtp|null $otp */
        $otp = $query->first();

        if (! $otp || $otp->expires_at->isPast()) {
            return null;
        }

        $subject = $otp->admin_id ? $otp->admin : $otp->user;

        if (! $subject instanceof User && ! $subject instanceof Admin) {
            return null;
        }

        $otp->update(['consumed_at' => now()]);

        $this->markRegistrationVerified($subject);

        $token = $subject instanceof Admin
            ? $this->issueAdminAccessToken($subject)
            : $this->issueAccessToken($subject);

        return [
            'user' => $subject,
            'token' => $token,
        ];
    }

    public function requestPhoneLoginOtp(string $phone, ?string $role = null): array
    {
        $user = $this->resolveUserByPhone($phone);

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'phone' => ['No account found with this phone number.'],
            ]);
        }

        if ($user->role === 'admin') {
            throw ValidationException::withMessages([
                'phone' => ['Admins must use the admin login URL.'],
            ]);
        }

        if ($user->status === UserStatus::Block->value) {
            throw ValidationException::withMessages([
                'phone' => ['This account has been blocked. Please contact support.'],
            ]);
        }

        if ($role !== null && $role !== '' && $user->role !== $role) {
            throw ValidationException::withMessages([
                'phone' => ["This account is registered as a {$user->role}. Please log in with the correct account type."],
            ]);
        }

        if (! $user->phone) {
            throw ValidationException::withMessages([
                'phone' => ['This account does not have a phone number on file.'],
            ]);
        }

        $otp = $this->issueOtp($user, OtpPurpose::Login);
        $smsDelivered = $this->deliverOtpSms($user, $otp->code, OtpPurpose::Login, allowEmailFallback: false);

        return [
            'user' => $user,
            'otp' => $otp,
            'masked_phone' => PhoneNormalizer::mask($user->phone),
            'sms_delivered' => $smsDelivered,
        ];
    }

    public function verifyPhoneLoginOtp(string $phone, string $code, ?string $role = null): array
    {
        $user = $this->resolveUserByPhone($phone);

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired OTP.'],
            ]);
        }

        if ($user->role === 'admin') {
            throw ValidationException::withMessages([
                'code' => ['Admins must use the admin login URL.'],
            ]);
        }

        if ($user->status === UserStatus::Block->value) {
            throw ValidationException::withMessages([
                'code' => ['This account has been blocked. Please contact support.'],
            ]);
        }

        if ($role !== null && $role !== '' && $user->role !== $role) {
            throw ValidationException::withMessages([
                'code' => ["This account is registered as a {$user->role}. Please log in with the correct account type."],
            ]);
        }

        /** @var AuthOtp|null $otp */
        $otp = AuthOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', OtpPurpose::Login->value)
            ->where('code', hash('sha256', $code))
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp instanceof AuthOtp || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired OTP.'],
            ]);
        }

        $otp->update(['consumed_at' => now()]);

        if (! $user->phone_verified_at) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        if (! $user->email_verified_at) {
            $user->forceFill([
                'email_verified_at' => now(),
                'status' => UserStatus::Active->value,
            ])->save();
            $user->refresh();
        }

        if ($this->twoFactor->isEnabled($user)) {
            $challengeToken = $this->initiateTwoFactorLogin($user, $role);

            return [
                'user' => $user,
                'two_factor_required' => true,
                'two_factor_token' => $challengeToken,
            ];
        }

        return [
            'user' => $user,
            'token' => $this->issueAccessToken($user),
            'two_factor_required' => false,
        ];
    }

    public function resendPhoneLoginOtp(string $phone, ?string $role = null): AuthOtp
    {
        ['otp' => $otp] = $this->requestPhoneLoginOtp($phone, $role);

        return $otp;
    }

    public function resolveLoginUserByCredentials(?string $email, ?string $phone, string $password): User
    {
        if (filled($phone)) {
            $user = $this->resolveUserByPhone($phone);

            if (! $user instanceof User) {
                throw new \Exception('No account found with this phone number.');
            }
        } elseif (filled($email)) {
            $user = User::query()
                ->where('email', Str::lower($email))
                ->first();

            if (! $user instanceof User) {
                throw new \Exception('No account found with this email address.');
            }
        } else {
            throw new \Exception('Email or phone number is required.');
        }

        if (! Hash::check($password, $user->password)) {
            throw new \Exception('Incorrect password.');
        }

        return $user;
    }

    public function resolveLoginUser(string $email, string $password): User
    {
        return $this->resolveLoginUserByCredentials($email, null, $password);
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

        $otp = $this->issueOtp($subject, OtpPurpose::ForgotPassword);
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

        if ($subject instanceof User && $subject->phone) {
            try {
                $this->deliverOtpSms($subject, $otp->code, OtpPurpose::ForgotPassword);
            } catch (\Throwable) {
                // Email delivery already attempted above.
            }
        }

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
            ->where('purpose', OtpPurpose::ForgotPassword->value)
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

        $otp = $this->issueOtp($subject, OtpPurpose::ForgotPassword);

        $this->deliverOtpMail($subject, $otp->code, true);

        if ($subject instanceof User && $subject->phone) {
            try {
                $this->deliverOtpSms($subject, $otp->code, OtpPurpose::ForgotPassword);
            } catch (\Throwable) {
                // Email delivery already attempted above.
            }
        }

        return $otp;
    }

    public function resendOtp(User|Admin $subject): AuthOtp
    {
        $otp = $this->issueOtp($subject, OtpPurpose::Register);

        if ($subject instanceof User) {
            $channel = $subject->registrationVerificationChannel()
                ?? ($subject->phone && ! $subject->email ? 'phone' : 'email');
            $this->deliverRegistrationOtp($subject, $otp->code, $channel);
        } else {
            $this->deliverOtpMail($subject, $otp->code, false);
        }

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

    private function issueOtp(User|Admin $subject, OtpPurpose $purpose = OtpPurpose::Register): AuthOtp
    {
        /** @var Builder<AuthOtp> $otpQuery */
        $otpQuery = AuthOtp::query();
        $otpQuery->whereNull('consumed_at')
            ->where('purpose', $purpose->value);

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
            'purpose' => $purpose->value,
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

    private function deliverRegistrationOtp(User $user, string $otpCode, string $channel): bool
    {
        if ($channel === 'phone') {
            return $this->deliverOtpSms($user, $otpCode, OtpPurpose::Register, allowEmailFallback: false);
        }

        try {
            $this->deliverOtpMail($user, $otpCode, false);

            return true;
        } catch (\Throwable $throwable) {
            Log::error('Registration OTP delivery failed.', [
                'user_id' => $user->id,
                'channel' => $channel,
                'error' => $throwable->getMessage(),
            ]);

            return false;
        }
    }

    private function markRegistrationVerified(User|Admin $subject): void
    {
        if ($subject instanceof Admin) {
            if (! $subject->email_verified_at) {
                $subject->forceFill([
                    'email_verified_at' => now(),
                    'status' => AdminStatus::Active,
                ])->save();
            }

            return;
        }

        if ($subject->isAccountVerified()) {
            if ($subject->status !== UserStatus::Active->value) {
                $subject->forceFill(['status' => UserStatus::Active->value])->save();
            }

            return;
        }

        $channel = $subject->registrationVerificationChannel();
        $payload = ['status' => UserStatus::Active->value];

        if ($channel === 'phone') {
            $payload['phone_verified_at'] = now();
        } elseif ($channel === 'email') {
            $payload['email_verified_at'] = now();
        } else {
            if ($subject->email) {
                $payload['email_verified_at'] = now();
            }
            if ($subject->phone) {
                $payload['phone_verified_at'] = now();
            }
        }

        $subject->forceFill($payload)->save();
    }

    private function deliverOtpSms(
        User|Admin $subject,
        string $otpCode,
        OtpPurpose $purpose,
        bool $allowEmailFallback = true,
    ): bool {
        if (! $subject instanceof User || ! $subject->phone) {
            if ($allowEmailFallback && filled($subject->email)) {
                $this->deliverOtpMail($subject, $otpCode, $purpose === OtpPurpose::ForgotPassword);

                return true;
            }

            return false;
        }

        $context = match ($purpose) {
            OtpPurpose::Login => 'login',
            OtpPurpose::ForgotPassword => 'forgot_password',
            default => 'verification',
        };

        if (! $this->termii->isConfigured()) {
            Log::info('Termii not configured. Registration/login OTP logged for development.', [
                'user_id' => $subject->id,
                'phone' => PhoneNormalizer::mask($subject->phone),
                'purpose' => $purpose->value,
                'otp' => $otpCode,
            ]);

            if ($allowEmailFallback && filled($subject->email)) {
                $this->deliverOtpMail(
                    $subject,
                    $otpCode,
                    $purpose === OtpPurpose::ForgotPassword,
                );
            }

            return true;
        }

        $smsSent = $this->termii->sendOtp($subject->phone, $otpCode, $context);

        if ($smsSent) {
            return true;
        }

        if ($allowEmailFallback && filled($subject->email)) {
            $this->deliverOtpMail(
                $subject,
                $otpCode,
                $purpose === OtpPurpose::ForgotPassword,
            );

            return true;
        }

        Log::warning('OTP SMS was not delivered and no email fallback is available.', [
            'user_id' => $subject->id,
            'phone' => PhoneNormalizer::mask($subject->phone),
            'purpose' => $purpose->value,
        ]);

        return false;
    }

    private function resolveUserByPhone(string $phone): ?User
    {
        $variants = PhoneNormalizer::variants($phone);

        if ($variants === []) {
            return null;
        }

        return User::query()
            ->where(function (Builder $query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhere('phone', $variant);
                }
            })
            ->first();
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

        if (! filled($subject->email)) {
            Log::warning('OTP mail skipped because account has no email address.', $context);

            return;
        }

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
