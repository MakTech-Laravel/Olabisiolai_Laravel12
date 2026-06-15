<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\PhoneLoginRequestOtpRequest;
use App\Http\Requests\Api\V1\PhoneVerifyOtpRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResendForgotPasswordOtpRequest;
use App\Http\Requests\Api\V1\ResendNewDeviceOtpRequest;
use App\Http\Requests\Api\V1\ResendRegistrationOtpRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\VerifyForgotPasswordTokenRequest;
use App\Http\Requests\Api\V1\VerifyNewDeviceOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Requests\Api\V1\VerifyTwoFactorLoginRequest;
use App\Http\Resources\Api\V1\AdminResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Admin;
use App\Models\User;
use App\Services\AuthService;
use App\Services\TwoFactorAuthenticationService;
use App\Support\LoginRoleCompatibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function testTermii(Request $request)
    {
        $otp = rand(100000, 999999);

        $response = Http::post(env('TERMII_BASE_URL') . '/api/sms/send', [
            'api_key' => env('TERMII_API_KEY'),
            'to'      => '2348153353131',
            'from'    => 'N-Alert',
            'sms'     => "Your Gidira OTP is {$otp}. Do not share with anyone.",
            'type'    => 'plain',
            'channel' => 'dnd',
        ]);


        if ($response->successful()) {
            cache()->put('otp_' . '01792973431', $otp, now()->addMinutes(5));
            return sendResponse(true, 'OTP sent', null, Response::HTTP_OK);
        }


        return sendResponse(false, 'Failed to send OTP', null, Response::HTTP_INTERNAL_SERVER_ERROR, ['errors' => $response->json()]);
    }

    public function register(RegisterRequest $request)
    {
        try {
            ['user' => $user, 'otp' => $otp, 'verification_channel' => $channel] = $this->authService->register($request->validated());

            return sendResponse(true, 'Registration successful. Verify OTP to activate your account.', [
                'verification_status' => 'unverified',
                'verification_channel' => $channel,
                'otp' => $otp->code,
                'user' => UserResource::make($user),
            ], Response::HTTP_CREATED);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifyForgotPasswordToken(VerifyForgotPasswordTokenRequest $request)
    {
        try {
            $validated = $request->validated();
            $isValid = $this->authService->verifyForgotPasswordToken(
                $validated['email'] ?? null,
                $validated['phone'] ?? null,
                $validated['token'],
                $validated['role'] ?? null,
            );

            if (! $isValid) {
                return sendResponse(false, 'Invalid or expired reset token. Please request a new reset OTP.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return sendResponse(true, 'Reset token is valid. You can proceed to OTP verification and password reset.');
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        try {
            $validated = $request->validated();
            $authUser = $request->user('api');
            $result = $this->authService->verifyOtp(
                (string) $validated['code'],
                isset($validated['phone']) ? (string) $validated['phone'] : null,
                $authUser instanceof User ? $authUser : null,
                isset($validated['email']) ? (string) $validated['email'] : null,
            );

            if (! $result) {
                return sendResponse(false, 'Invalid or expired OTP.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $verifiedUser = $result['user'] ?? null;

            if ($verifiedUser instanceof User) {
                $this->authService->rememberTrustedDevice(
                    $verifiedUser,
                    isset($validated['device_id']) ? (string) $validated['device_id'] : null,
                    isset($validated['device_name']) ? (string) $validated['device_name'] : null,
                );
            }

            return sendResponse(true, 'OTP verified successfully. You are logged in.', [
                'verification_status' => 'verified',
                'token' => $result['token'] ?? null,
                'user' => $verifiedUser instanceof User
                    ? UserResource::make($verifiedUser)
                    : ($verifiedUser instanceof Admin ? AdminResource::make($verifiedUser) : null),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function requestPhoneLoginOtp(PhoneLoginRequestOtpRequest $request)
    {
        try {
            $validated = $request->validated();

            $result = $this->authService->requestPhoneLoginOtp(
                $validated['phone'],
                $validated['role'] ?? null,
            );

            $message = ($result['sms_delivered'] ?? true)
                ? 'OTP sent successfully to your phone number.'
                : 'OTP generated. SMS could not be delivered — verify your Termii sender ID or check Laravel logs in local dev.';

            return sendResponse(true, $message, [
                'masked_phone' => $result['masked_phone'],
                'sms_delivered' => $result['sms_delivered'] ?? true,
                'otp' => $result['otp']->code,
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifyPhoneLoginOtp(PhoneVerifyOtpRequest $request)
    {
        try {
            $validated = $request->validated();

            $result = $this->authService->verifyPhoneLoginOtp(
                $validated['phone'],
                $validated['code'],
                $validated['role'] ?? null,
            );

            if ($result['two_factor_required']) {
                return sendResponse(true, 'Two-factor authentication required.', [
                    'two_factor_required' => true,
                    'two_factor_token' => $result['two_factor_token'],
                    'verification_status' => 'two_factor_required',
                ]);
            }

            $this->authService->rememberTrustedDevice(
                $result['user'],
                isset($validated['device_id']) ? (string) $validated['device_id'] : null,
                isset($validated['device_name']) ? (string) $validated['device_name'] : null,
            );

            return sendResponse(true, 'Login successful.', [
                'token' => $result['token'],
                'verification_status' => 'verified',
                'user' => UserResource::make($result['user']),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resendPhoneLoginOtp(PhoneLoginRequestOtpRequest $request)
    {
        try {
            $validated = $request->validated();
            $otp = $this->authService->resendPhoneLoginOtp(
                $validated['phone'],
                $validated['role'] ?? null,
            );

            return sendResponse(true, 'A new OTP has been sent to your phone number.', [
                'otp' => $otp->code,
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function login(LoginRequest $request)
    {
        try {
            $validated = $request->validated();

            try {
                $user = $this->authService->resolveLoginUserByCredentials(
                    $validated['email'] ?? null,
                    $validated['phone'] ?? null,
                    $validated['password'],
                );
            } catch (\Exception $e) {
                return sendResponse(false, $e->getMessage(), null, Response::HTTP_UNAUTHORIZED);
            }

            if ($user->role === 'admin') {
                return sendResponse(false, 'Admins must use the admin login URL.', null, Response::HTTP_FORBIDDEN);
            }

            if (isset($validated['role']) && ! LoginRoleCompatibility::matches($validated['role'], $user->role)) {
                return sendResponse(false, "This account is registered as a {$user->role}. Please log in with role: {$user->role}.", null, Response::HTTP_FORBIDDEN);
            }

            if (! $user->isAccountVerified()) {
                $channel = $user->registrationVerificationChannel()
                    ?? ($user->phone && ! $user->email ? 'phone' : 'email');
                $destination = $channel === 'phone' ? 'phone number' : 'email';

                return sendResponse(
                    false,
                    "Please verify your account to continue. Use the signup verification code sent to your {$destination}, or request a new code on the verification page.",
                    [
                        'verification_status' => 'unverified',
                        'verification_required' => true,
                        'verification_channel' => $channel,
                        'user' => UserResource::make($user),
                    ],
                    Response::HTTP_FORBIDDEN,
                );
            }

            $deviceId = isset($validated['device_id']) ? (string) $validated['device_id'] : null;
            $deviceName = isset($validated['device_name']) ? (string) $validated['device_name'] : null;

            if (! $this->authService->isTrustedDevice($user, $deviceId)) {
                $verificationChannel = filled($validated['phone']) ? 'phone' : 'email';
                $challenge = $this->authService->initiateNewDeviceLogin(
                    $user,
                    (string) $deviceId,
                    $deviceName,
                    $validated['role'] ?? null,
                    $verificationChannel,
                );

                return sendResponse(true, 'Please verify this device to continue.', [
                    'device_verification_required' => true,
                    'device_verification_token' => $challenge['token'],
                    'verification_channel' => $challenge['channel'],
                    'verification_status' => 'device_verification_required',
                    'masked_email' => $challenge['masked_email'],
                    'masked_phone' => $challenge['masked_phone'],
                    'otp' => $challenge['otp']->code,
                    'user' => UserResource::make($user),
                ]);
            }

            if ($this->twoFactor->isEnabled($user)) {
                $challengeToken = $this->authService->initiateTwoFactorLogin(
                    $user,
                    $validated['role'] ?? null,
                );

                return sendResponse(true, 'Two-factor authentication required.', [
                    'two_factor_required' => true,
                    'two_factor_token' => $challengeToken,
                    'verification_status' => 'two_factor_required',
                ]);
            }

            $token = $this->authService->issueAccessToken($user);
            $this->authService->touchTrustedDevice($user, $deviceId);

            return sendResponse(true, 'Login successful.', [
                'token' => $token,
                'verification_status' => 'verified',
                'user' => UserResource::make($user),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifyTwoFactorLogin(VerifyTwoFactorLoginRequest $request)
    {
        try {
            $validated = $request->validated();

            $user = $this->authService->completeTwoFactorLogin(
                $validated['two_factor_token'],
                $validated['code'],
            );

            if ($user->role === 'admin') {
                return sendResponse(false, 'Admins must use the admin login URL.', null, Response::HTTP_FORBIDDEN);
            }

            if (isset($validated['role']) && ! LoginRoleCompatibility::matches($validated['role'], $user->role)) {
                return sendResponse(
                    false,
                    "This account is registered as a {$user->role}. Please log in with role: {$user->role}.",
                    null,
                    Response::HTTP_FORBIDDEN,
                );
            }

            $token = $this->authService->issueAccessToken($user);
            $this->authService->rememberTrustedDevice(
                $user,
                isset($validated['device_id']) ? (string) $validated['device_id'] : null,
                isset($validated['device_name']) ? (string) $validated['device_name'] : null,
            );

            return sendResponse(true, 'Login successful.', [
                'token' => $token,
                'verification_status' => 'verified',
                'user' => UserResource::make($user),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifyNewDeviceLogin(VerifyNewDeviceOtpRequest $request)
    {
        try {
            $validated = $request->validated();

            $result = $this->authService->completeNewDeviceLogin(
                $validated['device_verification_token'],
                $validated['code'],
                $validated['role'] ?? null,
            );

            $user = $result['user'];

            if ($user->role === 'admin') {
                return sendResponse(false, 'Admins must use the admin login URL.', null, Response::HTTP_FORBIDDEN);
            }

            if (isset($validated['role']) && ! LoginRoleCompatibility::matches($validated['role'], $user->role)) {
                return sendResponse(
                    false,
                    "This account is registered as a {$user->role}. Please log in with role: {$user->role}.",
                    null,
                    Response::HTTP_FORBIDDEN,
                );
            }

            if ($result['two_factor_required']) {
                return sendResponse(true, 'Two-factor authentication required.', [
                    'two_factor_required' => true,
                    'two_factor_token' => $result['two_factor_token'],
                    'verification_status' => 'two_factor_required',
                ]);
            }

            return sendResponse(true, 'Login successful.', [
                'token' => $result['token'],
                'verification_status' => 'verified',
                'user' => UserResource::make($user),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resendNewDeviceLoginOtp(ResendNewDeviceOtpRequest $request)
    {
        try {
            $validated = $request->validated();
            $otp = $this->authService->resendNewDeviceLoginOtp($validated['device_verification_token']);

            return sendResponse(true, 'A new verification code has been sent.', [
                'otp' => $otp->code,
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function adminLogin(LoginRequest $request)
    {
        try {
            $validated = $request->validated();
            try {
                $admin = $this->authService->resolveAdminLoginUser($validated['email'], $validated['password']);
            } catch (\Exception $e) {
                return sendResponse(false, $e->getMessage(), null, Response::HTTP_UNAUTHORIZED);
            }
            if (! $admin->email_verified_at) {
                return sendResponse(false, 'Please verify your account via OTP before logging in.', [
                    'verification_status' => 'unverified',
                ], Response::HTTP_FORBIDDEN);
            }

            $token = $this->authService->issueAdminAccessToken($admin);

            return sendResponse(true, 'Admin login successful.', [
                'token' => $token,
                'verification_status' => 'verified',
                'admin' => AdminResource::make($admin),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            $validated = $request->validated();
            $result = $this->authService->forgotPassword(
                $validated['email'] ?? null,
                $validated['phone'] ?? null,
                $validated['role'] ?? null,
            );

            if (! isset($result)) {
                $message = filled($validated['phone'] ?? null)
                    ? 'No account was found with this phone number.'
                    : 'No account was found with this email address.';

                return sendResponse(false, $message, null, Response::HTTP_NOT_FOUND);
            }

            $deliveryMessage = ($result['verification_channel'] ?? 'email') === 'phone'
                ? 'Reset OTP sent successfully to your phone number.'
                : 'Reset OTP sent successfully to your email address.';

            return sendResponse(true, $deliveryMessage, [
                'Otp' => ! isset($result) ? null : $result['otp']->code,
                'Token' => ! isset($result) ? null : $result['token'],
                'verification_channel' => $result['verification_channel'] ?? 'email',
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resendForgotPasswordOtp(ResendForgotPasswordOtpRequest $request)
    {
        try {
            $validated = $request->validated();
            $otp = $this->authService->resendForgotPasswordOtp(
                $validated['email'] ?? null,
                $validated['phone'] ?? null,
                $validated['token'],
                $validated['role'] ?? null,
            );

            if (! $otp) {
                return sendResponse(false, 'Invalid or expired reset token. Please request a new password reset.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $message = filled($validated['phone'] ?? null)
                ? 'A new OTP has been sent to your phone number.'
                : 'A new OTP has been sent to your email address.';

            return sendResponse(true, $message, [
                'Otp' => $otp->code,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifyForgotPasswordOtp(VerifyForgotPasswordTokenRequest $request)
    {
        try {
            $validated = $request->validated();
            $isValid = $this->authService->verifyForgotPasswordOtp(
                $validated['email'] ?? null,
                $validated['phone'] ?? null,
                $validated['code'],
                $validated['token'],
                $validated['role'] ?? null,
            );

            if (! $isValid) {
                return sendResponse(false, 'Invalid or expired OTP/token combination. Please request a new reset OTP.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return sendResponse(true, 'OTP verified successfully. You can now reset your password.');
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $validated = $request->validated();
            if (! $this->authService->resetPasswordByEmail(
                $validated['email'] ?? null,
                $validated['phone'] ?? null,
                $validated['password'],
                $validated['token'],
                $validated['role'] ?? null,
            )) {
                return sendResponse(false, 'Unable to reset password. The reset token is invalid, expired, or does not match your account.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return sendResponse(true, 'Password reset successful. You can now log in with your new password.');
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resendRegistrationOtp(ResendRegistrationOtpRequest $request)
    {
        try {
            $validated = $request->validated();
            $otp = $this->authService->resendRegistrationOtpForContact(
                isset($validated['email']) ? (string) $validated['email'] : null,
                isset($validated['phone']) ? (string) $validated['phone'] : null,
            );

            if (! $otp) {
                return sendResponse(
                    false,
                    'We could not resend a verification code for this account. Check your details or register again.',
                    null,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            return sendResponse(true, 'OTP resent successfully.', [
                'verification_status' => 'unverified',
                'otp' => $otp->code,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resendOtp(Request $request)
    {
        try {
            $user = $request->user('api') ?? $request->user('admin_api');

            if (! $user instanceof User && ! $user instanceof Admin) {
                return sendResponse(false, 'Authentication is required to resend OTP.', null, Response::HTTP_UNAUTHORIZED);
            }

            if ($user->isAccountVerified()) {
                return sendResponse(true, 'Account already verified.', [
                    'verification_status' => 'verified',
                ]);
            }

            $otp = $this->authService->resendOtp($user);

            return sendResponse(true, 'OTP resent successfully.', [
                'verification_status' => 'unverified',
                'Otp' => $otp->code,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user('api') ?? $request->user('admin_api');

            if ($user instanceof User || $user instanceof Admin) {
                $user->tokens()->delete();
            }

            $role = $user instanceof Admin ? 'admin' : ($user?->role ?? 'guest');
            $messages = [
                'admin' => 'Admin logged out successfully.',
                'vendor' => 'Vendor logged out successfully.',
                'user' => 'User logged out successfully.',
            ];

            $message = $messages[$role] ?? 'Logged out successfully.';

            return sendResponse(true, $message, [
                'role' => $role,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function profile(Request $request)
    {
        try {
            // Prefer admin guard so a Passport token never resolves to the wrong model if IDs overlap.
            $authUser = $request->user('admin_api') ?? $request->user('api');

            if ($authUser instanceof Admin) {
                return sendResponse(true, 'Profile retrieved successfully.', AdminResource::make($authUser));
            }

            return sendResponse(true, 'Profile retrieved successfully.', UserResource::make($authUser));
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
