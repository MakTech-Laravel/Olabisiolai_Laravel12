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
use App\Support\LoginRoleCompatibility;
use App\Support\PhoneNormalizer;
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

    private const NEW_DEVICE_LOGIN_TTL_MINUTES = 10;

    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
        private readonly TermiiService $termii,
        private readonly WelcomeEmailService $welcomeEmail,
        private readonly TrustedDeviceService $trustedDevices,
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
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
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

        $this->deliverRegistrationOtp($user, $otp->code, $channel);

        return [
            'user' => $user,
            'otp' => $otp,
            'verification_channel' => $channel,
        ];
    }

    /**
     * @deprecated Login no longer sends OTP. Unverified users must complete signup verification.
     *
     * @return array{user: User, otp: AuthOtp, token: string, verification_channel: string}
     */
    public function initiateLoginVerification(User $user): array
    {
        $otp = $this->resendOtp($user);
        $channel = $user->registrationVerificationChannel()
            ?? ($user->phone && ! $user->email ? 'phone' : 'email');

        return [
            'user' => $user,
            'otp' => $otp,
            'token' => $this->issueAccessToken($user),
            'verification_channel' => $channel,
        ];
    }

    public function verifyOtp(
        string $code,
        ?string $phone = null,
        ?User $authenticatedUser = null,
        ?string $email = null,
    ): ?array {
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
        } elseif ($email !== null) {
            $user = $this->resolveUserByEmail($email);
            if (! $user instanceof User) {
                return null;
            }
            $query->where('user_id', $user->id)
                ->where('purpose', OtpPurpose::Register->value);
        } else {
            return null;
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

        if ($role !== null && $role !== '' && ! LoginRoleCompatibility::matches($role, $user->role)) {
            throw ValidationException::withMessages([
                'phone' => ["This account is registered as a {$user->role}. Please log in with the correct account type."],
            ]);
        }

        if (! $user->isAccountVerified()) {
            throw ValidationException::withMessages([
                'phone' => ['Please verify your account first. Use the signup verification code sent to your email or phone.'],
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

        if ($role !== null && $role !== '' && ! LoginRoleCompatibility::matches($role, $user->role)) {
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

        if (! $user->email_verified_at && filled($user->email)) {
            $user->forceFill([
                'email_verified_at' => now(),
                'status' => UserStatus::Active->value,
            ])->save();
            $user->refresh();
        }

        if ($this->twoFactor->isEnabled($user)) {
            $challenge = $this->initiateTwoFactorLogin($user, $role);

            return [
                'user' => $user,
                'two_factor_required' => true,
                'two_factor_token' => $challenge['token'],
                'two_factor_channel' => $challenge['verification_channel'],
                'two_factor_masked_email' => $challenge['masked_email'],
                'two_factor_masked_phone' => $challenge['masked_phone'],
                'two_factor_otp' => $challenge['otp'],
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

    /**
     * @return array{
     *     token: string,
     *     verification_channel: string,
     *     masked_email: string|null,
     *     masked_phone: string|null,
     *     otp: AuthOtp|null,
     * }
     */
    public function initiateTwoFactorLogin(User $user, ?string $expectedRole = null): array
    {
        $token = Str::random(64);

        Cache::put($this->twoFactorLoginCacheKey($token), [
            'user_id' => $user->id,
            'expected_role' => $expectedRole,
        ], now()->addMinutes(self::TWO_FACTOR_LOGIN_TTL_MINUTES));

        $delivery = $this->deliverTwoFactorLoginOtp($user);

        return [
            'token' => $token,
            'verification_channel' => $delivery['channel'],
            'masked_email' => $delivery['masked_email'],
            'masked_phone' => $delivery['masked_phone'],
            'otp' => $delivery['otp'],
        ];
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

        if (! $this->twoFactor->verify($user, $code) && ! $this->consumeTwoFactorLoginOtp($user, $code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided two factor authentication code was invalid.'],
            ]);
        }

        $expectedRole = $payload['expected_role'] ?? null;
        if (is_string($expectedRole) && $expectedRole !== '' && ! LoginRoleCompatibility::matches($expectedRole, $user->role)) {
            throw ValidationException::withMessages([
                'code' => ["This account is registered as a {$user->role}. Please log in with the correct account type."],
            ]);
        }

        Cache::forget($cacheKey);

        return $user;
    }

    /**
     * @return array{
     *     token: string,
     *     verification_channel: string,
     *     masked_email: string|null,
     *     masked_phone: string|null,
     *     otp: AuthOtp|null,
     * }
     */
    public function initiateAdminTwoFactorLogin(Admin $admin): array
    {
        $token = Str::random(64);

        Cache::put($this->twoFactorLoginCacheKey($token), [
            'account_type' => 'admin',
            'admin_id' => $admin->id,
        ], now()->addMinutes(self::TWO_FACTOR_LOGIN_TTL_MINUTES));

        $delivery = $this->deliverTwoFactorLoginOtp($admin);

        return [
            'token' => $token,
            'verification_channel' => $delivery['channel'],
            'masked_email' => $delivery['masked_email'],
            'masked_phone' => $delivery['masked_phone'],
            'otp' => $delivery['otp'],
        ];
    }

    public function completeAdminTwoFactorLogin(string $token, string $code): Admin
    {
        $cacheKey = $this->twoFactorLoginCacheKey($token);
        /** @var array{account_type?: string, admin_id?: int}|null $payload */
        $payload = Cache::get($cacheKey);

        if (
            ! is_array($payload)
            || ($payload['account_type'] ?? null) !== 'admin'
            || ! isset($payload['admin_id'])
        ) {
            throw ValidationException::withMessages([
                'two_factor_token' => ['Your login session has expired. Please sign in again.'],
            ]);
        }

        $admin = Admin::query()->find($payload['admin_id']);
        if (! $admin instanceof Admin) {
            Cache::forget($cacheKey);
            throw ValidationException::withMessages([
                'two_factor_token' => ['Your login session has expired. Please sign in again.'],
            ]);
        }

        if (! $this->twoFactor->isEnabled($admin)) {
            Cache::forget($cacheKey);
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication is not enabled for this account.'],
            ]);
        }

        if (! $this->twoFactor->verify($admin, $code) && ! $this->consumeTwoFactorLoginOtp($admin, $code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided two factor authentication code was invalid.'],
            ]);
        }

        Cache::forget($cacheKey);

        return $admin;
    }

    /**
     * @return array{
     *     token: string,
     *     channel: string,
     *     otp: AuthOtp,
     *     masked_email: string|null,
     *     masked_phone: string|null,
     * }
     */
    public function initiateNewDeviceLogin(
        User $user,
        string $deviceId,
        ?string $deviceName,
        ?string $expectedRole,
        string $verificationChannel,
    ): array {
        $otp = $this->issueOtp($user, OtpPurpose::NewDevice);

        if ($verificationChannel === 'phone') {
            $this->deliverOtpSms($user, $otp->code, OtpPurpose::NewDevice, allowEmailFallback: true);
        } elseif (filled($user->email)) {
            $this->deliverOtpMail($user, $otp->code, false);
        } elseif ($user->phone) {
            $this->deliverOtpSms($user, $otp->code, OtpPurpose::NewDevice, allowEmailFallback: false);
            $verificationChannel = 'phone';
        }

        $token = Str::random(64);

        Cache::put($this->newDeviceLoginCacheKey($token), [
            'user_id' => $user->id,
            'expected_role' => $expectedRole,
            'device_id' => trim($deviceId),
            'device_name' => $deviceName,
            'verification_channel' => $verificationChannel,
        ], now()->addMinutes(self::NEW_DEVICE_LOGIN_TTL_MINUTES));

        return [
            'token' => $token,
            'channel' => $verificationChannel,
            'otp' => $otp,
            'masked_email' => filled($user->email) ? $this->maskEmail($user->email) : null,
            'masked_phone' => $user->phone ? PhoneNormalizer::mask($user->phone) : null,
        ];
    }

    /**
     * @return array{
     *     user: User,
     *     token?: string,
     *     two_factor_required: bool,
     *     two_factor_token?: string,
     * }
     */
    public function completeNewDeviceLogin(string $token, string $code, ?string $expectedRole = null): array
    {
        $cacheKey = $this->newDeviceLoginCacheKey($token);
        /** @var array{
         *     user_id: int,
         *     expected_role?: string|null,
         *     device_id: string,
         *     device_name?: string|null,
         *     verification_channel?: string|null
         * }|null $payload */
        $payload = Cache::get($cacheKey);

        if (! is_array($payload) || ! isset($payload['user_id'], $payload['device_id'])) {
            throw ValidationException::withMessages([
                'device_verification_token' => ['Your verification session has expired. Please sign in again.'],
            ]);
        }

        $user = User::query()->find($payload['user_id']);
        if (! $user instanceof User) {
            Cache::forget($cacheKey);
            throw ValidationException::withMessages([
                'device_verification_token' => ['Your verification session has expired. Please sign in again.'],
            ]);
        }

        $resolvedRole = $expectedRole ?? ($payload['expected_role'] ?? null);
        if (is_string($resolvedRole) && $resolvedRole !== '' && ! LoginRoleCompatibility::matches($resolvedRole, $user->role)) {
            throw ValidationException::withMessages([
                'code' => ["This account is registered as a {$user->role}. Please log in with the correct account type."],
            ]);
        }

        /** @var AuthOtp|null $otp */
        $otp = AuthOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', OtpPurpose::NewDevice->value)
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
        Cache::forget($cacheKey);

        $this->trustedDevices->remember(
            $user,
            (string) $payload['device_id'],
            isset($payload['device_name']) ? (string) $payload['device_name'] : null,
        );

        if ($this->twoFactor->isEnabled($user)) {
            $challenge = $this->initiateTwoFactorLogin($user, $resolvedRole);

            return [
                'user' => $user,
                'two_factor_required' => true,
                'two_factor_token' => $challenge['token'],
                'two_factor_channel' => $challenge['verification_channel'],
                'two_factor_masked_email' => $challenge['masked_email'],
                'two_factor_masked_phone' => $challenge['masked_phone'],
                'two_factor_otp' => $challenge['otp'],
            ];
        }

        return [
            'user' => $user,
            'token' => $this->issueAccessToken($user),
            'two_factor_required' => false,
        ];
    }

    public function resendNewDeviceLoginOtp(string $token): AuthOtp
    {
        $cacheKey = $this->newDeviceLoginCacheKey($token);
        /** @var array{user_id: int, verification_channel?: string|null}|null $payload */
        $payload = Cache::get($cacheKey);

        if (! is_array($payload) || ! isset($payload['user_id'])) {
            throw ValidationException::withMessages([
                'device_verification_token' => ['Your verification session has expired. Please sign in again.'],
            ]);
        }

        $user = User::query()->find($payload['user_id']);
        if (! $user instanceof User) {
            Cache::forget($cacheKey);
            throw ValidationException::withMessages([
                'device_verification_token' => ['Your verification session has expired. Please sign in again.'],
            ]);
        }

        $channel = (string) ($payload['verification_channel'] ?? 'email');
        $otp = $this->issueOtp($user, OtpPurpose::NewDevice);

        if ($channel === 'phone') {
            $this->deliverOtpSms($user, $otp->code, OtpPurpose::NewDevice, allowEmailFallback: true);
        } elseif (filled($user->email)) {
            $this->deliverOtpMail($user, $otp->code, false);
        } elseif ($user->phone) {
            $this->deliverOtpSms($user, $otp->code, OtpPurpose::NewDevice, allowEmailFallback: false);
        }

        return $otp;
    }

    public function rememberTrustedDevice(User $user, ?string $deviceId, ?string $deviceName = null): void
    {
        if (! filled($deviceId)) {
            return;
        }

        $this->trustedDevices->remember($user, (string) $deviceId, $deviceName);
    }

    public function touchTrustedDevice(User $user, ?string $deviceId): void
    {
        if (! filled($deviceId)) {
            return;
        }

        $this->trustedDevices->touch($user, (string) $deviceId);
    }

    public function isTrustedDevice(User $user, ?string $deviceId): bool
    {
        if (! filled($deviceId)) {
            return true;
        }

        return $this->trustedDevices->isTrusted($user, (string) $deviceId);
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

    public function forgotPassword(?string $email, ?string $phone, ?string $role = null): ?array
    {
        $subject = $this->resolvePasswordResetSubject($email, $phone, $role);

        if (! $subject instanceof User && ! $subject instanceof Admin) {
            return null;
        }

        $otp = $this->issueOtp($subject, OtpPurpose::ForgotPassword);
        $plainToken = Str::random(64);
        $passwordBroker = $subject instanceof Admin ? 'admins' : 'users';
        $identifier = $this->passwordResetIdentifier($subject);
        $verificationChannel = filled($phone) ? 'phone' : 'email';

        DB::table($this->passwordResetTable($passwordBroker))->updateOrInsert(
            ['email' => $identifier],
            [
                'token' => hash('sha256', $plainToken),
                'created_at' => now(),
            ]
        );

        $this->deliverPasswordResetOtp($subject, $otp->code, $verificationChannel);

        return [
            'otp' => $otp,
            'token' => $plainToken,
            'verification_channel' => $verificationChannel,
        ];
    }

    public function verifyForgotPasswordOtp(
        ?string $email,
        ?string $phone,
        string $code,
        string $token,
        ?string $role = null,
    ): bool {
        $subject = $this->resolvePasswordResetSubject($email, $phone, $role);

        if (
            (! $subject instanceof User && ! $subject instanceof Admin)
            || ! $this->isValidPasswordResetToken(
                $this->passwordResetIdentifier($subject),
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

    public function verifyForgotPasswordToken(?string $email, ?string $phone, string $token, ?string $role = null): bool
    {
        $subject = $this->resolvePasswordResetSubject($email, $phone, $role);

        if (! $subject instanceof User && ! $subject instanceof Admin) {
            return false;
        }

        return $this->isValidPasswordResetToken(
            $this->passwordResetIdentifier($subject),
            $token,
            $subject instanceof Admin ? 'admins' : 'users',
        );
    }

    public function resetPasswordByEmail(
        ?string $email,
        ?string $phone,
        string $password,
        string $token,
        ?string $role = null,
    ): bool {
        $subject = $this->resolvePasswordResetSubject($email, $phone, $role);
        $passwordBroker = $subject instanceof Admin ? 'admins' : 'users';
        $identifier = $subject instanceof User || $subject instanceof Admin
            ? $this->passwordResetIdentifier($subject)
            : null;

        if (
            (! $subject instanceof User && ! $subject instanceof Admin)
            || ! is_string($identifier)
            || ! $this->isValidPasswordResetToken($identifier, $token, $passwordBroker)
        ) {
            return false;
        }

        $subject->update(['password' => $password]);
        DB::table($this->passwordResetTable($passwordBroker))->where('email', $identifier)->delete();

        return true;
    }

    public function resendForgotPasswordOtp(
        ?string $email,
        ?string $phone,
        string $token,
        ?string $role = null,
    ): ?AuthOtp {
        $subject = $this->resolvePasswordResetSubject($email, $phone, $role);

        if (
            (! $subject instanceof User && ! $subject instanceof Admin)
            || ! $this->isValidPasswordResetToken(
                $this->passwordResetIdentifier($subject),
                $token,
                $subject instanceof Admin ? 'admins' : 'users',
            )
        ) {
            return null;
        }

        $otp = $this->issueOtp($subject, OtpPurpose::ForgotPassword);
        $verificationChannel = filled($phone) ? 'phone' : 'email';
        $this->deliverPasswordResetOtp($subject, $otp->code, $verificationChannel);

        return $otp;
    }

    public function resendRegistrationOtpForContact(?string $email, ?string $phone): ?AuthOtp
    {
        $user = null;

        if (filled($email)) {
            $user = $this->resolveUserByEmail($email);
        } elseif (filled($phone)) {
            $user = $this->resolveUserByPhone($phone);
        }

        if (! $user instanceof User || $user->isAccountVerified()) {
            return null;
        }

        return $this->resendOtp($user);
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

    public function setUserEmailAndSendVerificationOtp(User $user, string $email): AuthOtp
    {
        $normalizedEmail = Str::lower(trim($email));

        if ($user->email === $normalizedEmail && $user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'email' => ['This email is already verified on your account.'],
            ]);
        }

        $user->forceFill([
            'email' => $normalizedEmail,
            'email_verified_at' => null,
        ])->save();

        $otp = $this->issueOtp($user, OtpPurpose::EmailVerify);

        try {
            $this->deliverOtpMail($user, $otp->code, false);
        } catch (\Throwable $throwable) {
            Log::error('Profile email verification OTP delivery failed.', [
                'user_id' => $user->id,
                'email' => $normalizedEmail,
                'error' => $throwable->getMessage(),
            ]);
        }

        return $otp;
    }

    public function setUserEmailForPurchase(User $user, string $email): User
    {
        $normalizedEmail = Str::lower(trim($email));

        $user->forceFill([
            'email' => $normalizedEmail,
            'email_verified_at' => now(),
        ])->save();

        return $user->refresh();
    }

    public function verifyUserEmailOtp(User $user, string $code): User
    {
        if (! filled($user->email)) {
            throw ValidationException::withMessages([
                'email' => ['Add an email address before verifying.'],
            ]);
        }

        /** @var AuthOtp|null $otp */
        $otp = AuthOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', OtpPurpose::EmailVerify->value)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp instanceof AuthOtp || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired OTP.'],
            ]);
        }

        if (! hash_equals((string) $otp->code, hash('sha256', $code))) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired OTP.'],
            ]);
        }

        $otp->update(['consumed_at' => now()]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user->refresh();
    }

    public function resendUserEmailVerificationOtp(User $user): AuthOtp
    {
        if (! filled($user->email)) {
            throw ValidationException::withMessages([
                'email' => ['Add an email address before requesting a verification code.'],
            ]);
        }

        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'email' => ['This email is already verified.'],
            ]);
        }

        $otp = $this->issueOtp($user, OtpPurpose::EmailVerify);

        try {
            $this->deliverOtpMail($user, $otp->code, false);
        } catch (\Throwable $throwable) {
            Log::error('Profile email verification OTP resend failed.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $throwable->getMessage(),
            ]);
        }

        return $otp;
    }

    /**
     * @return array{
     *     verification_channel: string,
     *     masked_email: string|null,
     *     masked_phone: string|null,
     *     otp: AuthOtp|null,
     * }
     */
    public function resendTwoFactorLoginOtp(string $token): array
    {
        $cacheKey = $this->twoFactorLoginCacheKey($token);
        /** @var array{account_type?: string, admin_id?: int, user_id?: int}|null $payload */
        $payload = Cache::get($cacheKey);

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'two_factor_token' => ['Your login session has expired. Please sign in again.'],
            ]);
        }

        if (($payload['account_type'] ?? null) === 'admin' && isset($payload['admin_id'])) {
            $admin = Admin::query()->find($payload['admin_id']);
            if (! $admin instanceof Admin) {
                Cache::forget($cacheKey);
                throw ValidationException::withMessages([
                    'two_factor_token' => ['Your login session has expired. Please sign in again.'],
                ]);
            }

            $delivery = $this->deliverTwoFactorLoginOtp($admin);

            return [
                'verification_channel' => $delivery['channel'],
                'masked_email' => $delivery['masked_email'],
                'masked_phone' => $delivery['masked_phone'],
                'otp' => $delivery['otp'],
            ];
        }

        if (! isset($payload['user_id'])) {
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

        $delivery = $this->deliverTwoFactorLoginOtp($user);

        return [
            'verification_channel' => $delivery['channel'],
            'masked_email' => $delivery['masked_email'],
            'masked_phone' => $delivery['masked_phone'],
            'otp' => $delivery['otp'],
        ];
    }

    public function issueAccessToken(User $user): string
    {
        return $user->createToken('Auth Token')->accessToken;
    }

    private function twoFactorLoginCacheKey(string $token): string
    {
        return 'two_factor_login:'.hash('sha256', $token);
    }

    /**
     * @return array{
     *     channel: string,
     *     masked_email: string|null,
     *     masked_phone: string|null,
     *     otp: AuthOtp|null,
     * }
     */
    private function deliverTwoFactorLoginOtp(User|Admin $account): array
    {
        $otp = $this->issueOtp($account, OtpPurpose::TwoFactorLogin);
        $channel = 'email';
        $maskedEmail = null;
        $maskedPhone = null;

        if ($account instanceof User && filled($account->phone) && ! filled($account->email)) {
            $channel = 'phone';
            $this->deliverOtpSms($account, $otp->code, OtpPurpose::TwoFactorLogin, allowEmailFallback: false);
            $maskedPhone = PhoneNormalizer::mask((string) $account->phone);
        } elseif (filled($account->email)) {
            $this->deliverOtpMail($account, $otp->code, false);
            $maskedEmail = $this->maskEmail((string) $account->email);
        } elseif ($account instanceof User && filled($account->phone)) {
            $channel = 'phone';
            $this->deliverOtpSms($account, $otp->code, OtpPurpose::TwoFactorLogin, allowEmailFallback: true);
            $maskedPhone = PhoneNormalizer::mask((string) $account->phone);
            if (filled($account->email)) {
                $maskedEmail = $this->maskEmail((string) $account->email);
            }
        }

        return [
            'channel' => $channel,
            'masked_email' => $maskedEmail,
            'masked_phone' => $maskedPhone,
            'otp' => $otp,
        ];
    }

    private function consumeTwoFactorLoginOtp(User|Admin $account, string $code): bool
    {
        $normalized = preg_replace('/\s+/', '', $code) ?? $code;

        $query = AuthOtp::query()
            ->whereNull('consumed_at')
            ->where('purpose', OtpPurpose::TwoFactorLogin->value)
            ->where('code', hash('sha256', $normalized))
            ->where('expires_at', '>', now());

        if ($account instanceof Admin) {
            $query->where('admin_id', $account->id);
        } else {
            $query->where('user_id', $account->id);
        }

        /** @var AuthOtp|null $otp */
        $otp = $query->first();
        if (! $otp) {
            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }

    private function newDeviceLoginCacheKey(string $token): string
    {
        return 'new_device_login:'.hash('sha256', $token);
    }

    private function maskEmail(string $email): string
    {
        $email = Str::lower(trim($email));
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        [$local, $domain] = $parts;
        if (strlen($local) <= 2) {
            return '***@'.$domain;
        }

        return substr($local, 0, 1).'***'.substr($local, -1).'@'.$domain;
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
            'expires_at' => now()->addMinutes($this->otpExpireMinutes()),
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

        $wasUnverified = ! $subject->isAccountVerified();

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

        if ($wasUnverified && $subject instanceof User) {
            $this->welcomeEmail->sendAfterRegistration($subject->fresh());
        }
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
            OtpPurpose::Login, OtpPurpose::NewDevice, OtpPurpose::TwoFactorLogin => 'login',
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

    private function otpExpireMinutes(): int
    {
        return max(5, (int) Config::get('auth.otp_expire_minutes', 15));
    }

    private function resolveUserByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', Str::lower(trim($email)))
            ->first();
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

    private function resolvePasswordResetSubject(?string $email, ?string $phone, ?string $role): User|Admin|null
    {
        if ($role === 'admin') {
            if (! filled($email)) {
                return null;
            }

            return Admin::query()->where('email', Str::lower($email))->first();
        }

        if (filled($phone)) {
            return $this->resolveUserByPhone($phone);
        }

        if (filled($email)) {
            return User::query()->where('email', Str::lower($email))->first();
        }

        return null;
    }

    private function passwordResetIdentifier(User|Admin $subject): string
    {
        if ($subject instanceof Admin) {
            return Str::lower($subject->email);
        }

        if (filled($subject->email)) {
            return Str::lower($subject->email);
        }

        return (string) $subject->phone;
    }

    private function deliverPasswordResetOtp(User|Admin $subject, string $otpCode, string $verificationChannel): void
    {
        if ($verificationChannel === 'phone' && $subject instanceof User && $subject->phone) {
            $this->deliverOtpSms($subject, $otpCode, OtpPurpose::ForgotPassword, allowEmailFallback: true);

            return;
        }

        if (filled($subject->email)) {
            $this->deliverOtpMail($subject, $otpCode, true);

            if ($subject instanceof User && $subject->phone) {
                try {
                    $this->deliverOtpSms($subject, $otpCode, OtpPurpose::ForgotPassword);
                } catch (\Throwable) {
                    // Email delivery already attempted above.
                }
            }

            return;
        }

        if ($subject instanceof User && $subject->phone) {
            $this->deliverOtpSms($subject, $otpCode, OtpPurpose::ForgotPassword, allowEmailFallback: false);
        }
    }

    private function passwordResetTable(string $passwordBroker): string
    {
        return (string) Config::get("auth.passwords.{$passwordBroker}.table", 'password_reset_tokens');
    }
}
