<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminResource;
use App\Models\Admin;
use App\Services\AuthService;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    #[OA\Post(
        path: '/v1/admin/login',
        summary: 'Log in as an admin',
        tags: ['Admin', 'Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Admin login successful, or a two-factor challenge was issued',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', nullable: true),
                    new OA\Property(property: 'token', ref: '#/components/schemas/AccessToken', nullable: true),
                    new OA\Property(property: 'admin', ref: '#/components/schemas/Admin', nullable: true),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                    new OA\Property(property: 'two_factor_required', type: 'boolean', nullable: true),
                    new OA\Property(property: 'two_factor_token', type: 'string', nullable: true),
                    new OA\Property(property: 'verification_status', type: 'string', nullable: true),
                ]),
            ),
            new OA\Response(response: 403, description: 'Admin account not yet email-verified', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'verification_status', type: 'string', example: 'unverified'),
            ])),
            new OA\Response(response: 422, description: 'Invalid credentials or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
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

    #[OA\Post(
        path: '/v1/admin/logout',
        summary: 'Log out the current admin and revoke their access tokens',
        tags: ['Admin', 'Auth'],
        security: [['passport' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function logout(Request $request)
    {
        ($request->user('admin_api') ?? $request->user('admin'))?->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out',
        ], Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/v1/admin/me',
        summary: 'Get the authenticated admin\'s profile, roles, and permissions',
        tags: ['Admin', 'Auth'],
        security: [['passport' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Admin profile retrieved',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'admin', ref: '#/components/schemas/Admin'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
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
