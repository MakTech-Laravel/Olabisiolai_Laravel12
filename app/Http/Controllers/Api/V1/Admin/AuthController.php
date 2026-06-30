<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminResource;
use App\Models\Admin;
use App\Services\AuthService;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $admin = $this->authService->resolveAdminLoginUser($validated['email'], $validated['password']);
        } catch (\Exception) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $admin->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your account via OTP before logging in.',
                'verification_status' => 'unverified',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($this->twoFactor->isEnabled($admin)) {
            $challenge = $this->authService->initiateAdminTwoFactorLogin($admin);

            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication required.',
                'two_factor_required' => true,
                'two_factor_token' => $challenge['token'],
                'verification_status' => 'two_factor_required',
                'verification_channel' => $challenge['verification_channel'],
                'masked_email' => $challenge['masked_email'],
                'masked_phone' => $challenge['masked_phone'],
                'otp' => $challenge['otp']?->code,
            ], Response::HTTP_OK);
        }

        $token = $this->authService->issueAdminAccessToken($admin);

        return response()->json([
            'success' => true,
            'token' => $token,
            'admin' => AdminResource::make($admin),
            'roles' => $admin->getRoleNames(),
            'permissions' => $admin->getAllPermissions()->pluck('name'),
        ], Response::HTTP_OK);
    }

    public function logout(Request $request)
    {
        ($request->user('admin_api') ?? $request->user('admin'))?->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out',
        ], Response::HTTP_OK);
    }

    public function me(Request $request)
    {
        /** @var Admin $admin */
        $admin = $request->user('admin_api') ?? $request->user('admin');

        return response()->json([
            'admin' => AdminResource::make($admin),
            'roles' => $admin->getRoleNames(),
            'permissions' => $admin->getAllPermissions()->pluck('name'),
        ], Response::HTTP_OK);
    }
}
