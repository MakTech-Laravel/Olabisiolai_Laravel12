<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResendForgotPasswordOtpRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\VerifyForgotPasswordTokenRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Requests\Api\V1\VerifyTwoFactorLoginRequest;
use App\Http\Resources\Api\V1\AdminResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Admin;
use App\Models\User;
use App\Services\AuthService;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function register(RegisterRequest $request)
    {
        try {
            ['user' => $user, 'otp' => $otp, 'token' => $token] = $this->authService->register($request->validated());

            return sendResponse(true, 'Registration successful. You are logged in. Verify OTP to activate your account.', [
                'verification_status' => 'unverified',
                'otp' => $otp->code,
                'token' => $token,
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
                $validated['email'],
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
            $result = $this->authService->verifyOtp((string) $request->validated()['code']);

            if (! $result) {
                return sendResponse(false, 'Invalid or expired OTP.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return sendResponse(true, 'OTP verified successfully. You are logged in.', [
                'verification_status' => 'verified',
            ]);
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
                $user = $this->authService->resolveLoginUser($validated['email'], $validated['password']);
            } catch (\Exception $e) {
                return sendResponse(false, $e->getMessage(), null, Response::HTTP_UNAUTHORIZED);
            }

            if ($user->role === 'admin') {
                return sendResponse(false, 'Admins must use the admin login URL.', null, Response::HTTP_FORBIDDEN);
            }

            if (isset($validated['role']) && $user->role !== $validated['role']) {
                return sendResponse(false, "This account is registered as a {$user->role}. Please log in with role: {$user->role}.", null, Response::HTTP_FORBIDDEN);
            }

            if (! $user->email_verified_at) {
                return sendResponse(false, 'Please verify your account via OTP before logging in.', [
                    'verification_status' => 'unverified',
                ], Response::HTTP_FORBIDDEN);
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

            if (isset($validated['role']) && $user->role !== $validated['role']) {
                return sendResponse(
                    false,
                    "This account is registered as a {$user->role}. Please log in with role: {$user->role}.",
                    null,
                    Response::HTTP_FORBIDDEN,
                );
            }

            $token = $this->authService->issueAccessToken($user);

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
                $validated['email'],
                $validated['role'] ?? null,
            );

            if (! isset($result)) {
                return sendResponse(false, 'No account was found with this email address.', null, Response::HTTP_NOT_FOUND);
            }

            return sendResponse(true, 'Reset OTP sent successfully. Verify OTP and use the reset token to set a new password.', [
                'Otp' => ! isset($result) ? null : $result['otp']->code,
                'Token' => ! isset($result) ? null : $result['token'],
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
                $validated['email'],
                $validated['token'],
                $validated['role'] ?? null,
            );

            if (! $otp) {
                return sendResponse(false, 'Invalid or expired reset token. Please request a new password reset.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return sendResponse(true, 'A new OTP has been sent to your email address.', [
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
                $validated['email'],
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
                $validated['email'],
                $validated['password'],
                $validated['token'],
                $validated['role'] ?? null,
            )) {
                return sendResponse(false, 'Unable to reset password. The reset token is invalid, expired, or does not match the email.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return sendResponse(true, 'Password reset successful. You can now log in with your new password.');
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

            if ($user->email_verified_at) {
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
