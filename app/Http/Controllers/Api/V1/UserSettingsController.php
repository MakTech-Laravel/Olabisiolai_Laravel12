<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangeUserPasswordRequest;
use App\Http\Requests\Api\V1\UpdateUserEmailRequest;
use App\Http\Requests\Api\V1\UpdateUserProfileRequest;
use App\Http\Requests\Api\V1\UpdateUserSettingsRequest;
use App\Http\Requests\Api\V1\VerifyUserEmailOtpRequest;
use App\Http\Traits\FileManagementTrait;
use App\Models\User;
use App\Services\AuthService;
use App\Services\BusinessInfoService;
use App\Services\UserFollowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserSettingsController extends Controller
{
    use FileManagementTrait;

    public function __construct(
        private readonly AuthService $authService,
        private readonly UserFollowService $userFollowService,
        private readonly BusinessInfoService $businessInfoService,
    ) {}

    #[OA\Get(
        path: '/v1/user/profile',
        summary: 'Get the authenticated user\'s profile',
        description: 'Restricted to accounts with role "user" (vendors use their own settings/dashboard endpoints).',
        tags: ['Users'],
        security: [['passport' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/UserProfile'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Access denied (not a user account)', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function profileShow(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $user?->isUser()) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $user->refresh();

            return sendResponse(true, 'Profile retrieved successfully.', $this->profilePayload($user));
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Patch(
        path: '/v1/user/profile',
        summary: 'Update the authenticated user\'s profile',
        tags: ['Users'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'first_name', type: 'string', maxLength: 120, nullable: true),
                new OA\Property(property: 'last_name', type: 'string', maxLength: 120, nullable: true),
                new OA\Property(property: 'phone', type: 'string', maxLength: 20, nullable: true),
            ]),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile updated successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/UserProfile'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Access denied (not a user account)', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function profileUpdate(UpdateUserProfileRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $user?->isUser()) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $validated = $request->validated();

            if (isset($validated['first_name'])) {
                $user->first_name = $validated['first_name'];
            }
            if (isset($validated['last_name'])) {
                $user->last_name = $validated['last_name'];
            }
            if (array_key_exists('phone', $validated)) {
                $user->phone = $validated['phone'];
            }

            if (isset($validated['first_name']) || isset($validated['last_name'])) {
                $user->name = trim($user->first_name.' '.$user->last_name);
            }

            $user->save();
            $user->refresh();

            return sendResponse(true, 'Profile updated successfully.', $this->profilePayload($user));
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/user/password',
        summary: 'Change the authenticated user\'s password',
        tags: ['Users'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ],
                example: [
                    'current_password' => 'OldPassw0rd123',
                    'password' => 'NewPassw0rd123',
                    'password_confirmation' => 'NewPassw0rd123',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password updated successfully', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Access denied (not a user account)', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(
                response: 422,
                description: 'Incorrect current password, new password matches old, or validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function changePassword(ChangeUserPasswordRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $user?->isUser()) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $validated = $request->validated();

            if (! Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }

            if (Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['Choose a password that is different from your current password.'],
                ]);
            }

            $user->password = $validated['password'];
            $user->save();

            return sendResponse(true, 'Password updated successfully.', null);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->validator->errors()->toArray(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Get(
        path: '/v1/user/settings',
        summary: 'Get the authenticated user\'s or vendor\'s account settings',
        description: 'Available before registration OTP is confirmed. Accessible to both "user" and "vendor" roles.',
        tags: ['Users'],
        security: [['passport' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Settings retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'profile', ref: '#/components/schemas/UserProfile'),
                        new OA\Property(property: 'settings', type: 'object'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Access denied', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $this->canAccessAccountSettings($user)) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $user->refresh();

            return sendResponse(true, 'User settings retrieved successfully.', $this->payload($user));
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Patch(
        path: '/v1/user/settings',
        summary: 'Update the authenticated user\'s or vendor\'s account settings',
        description: 'Accepts either PATCH with JSON, or POST with multipart/form-data when uploading an image '
            .'(PHP/nginx do not parse files on PATCH in production). Merges nested `settings` recursively rather than replacing it.',
        tags: ['Users'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(properties: [
                    new OA\Property(property: 'first_name', type: 'string', maxLength: 120, nullable: true),
                    new OA\Property(property: 'last_name', type: 'string', maxLength: 120, nullable: true),
                    new OA\Property(property: 'phone', type: 'string', maxLength: 20, nullable: true),
                    new OA\Property(property: 'wants_marketing_emails', type: 'boolean', nullable: true),
                    new OA\Property(property: 'location', type: 'string', maxLength: 255, nullable: true),
                    new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true, description: 'Max 10MB image file.'),
                    new OA\Property(
                        property: 'settings',
                        type: 'object',
                        nullable: true,
                        properties: [
                            new OA\Property(property: 'notifications', properties: [
                                new OA\Property(property: 'email', type: 'boolean'),
                                new OA\Property(property: 'push', type: 'boolean'),
                                new OA\Property(property: 'sms', type: 'boolean'),
                                new OA\Property(property: 'whatsapp', type: 'boolean'),
                            ], type: 'object'),
                            new OA\Property(property: 'active_business_id', type: 'integer', nullable: true),
                        ],
                    ),
                ]),
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Settings updated successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'profile', ref: '#/components/schemas/UserProfile'),
                        new OA\Property(property: 'settings', type: 'object'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Access denied', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function update(UpdateUserSettingsRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $this->canAccessAccountSettings($user)) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        $contentType = strtolower((string) $request->header('Content-Type', ''));
        if ($request->isMethod('PATCH') && str_contains($contentType, 'multipart/form-data')) {
            return sendResponse(
                false,
                'Profile image upload must use POST with multipart/form-data, not PATCH. Example: POST /api/v1/user/settings with form field "image".',
                null,
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $validated = $request->validated();

            if (isset($validated['first_name'])) {
                $user->first_name = $validated['first_name'];
            }
            if (isset($validated['last_name'])) {
                $user->last_name = $validated['last_name'];
            }
            if (array_key_exists('phone', $validated)) {
                $user->phone = $validated['phone'];
            }
            if (isset($validated['wants_marketing_emails'])) {
                $user->wants_marketing_emails = $validated['wants_marketing_emails'];
            }
            if (array_key_exists('location', $validated)) {
                $user->location = $validated['location'];
            }

            if (isset($validated['first_name']) || isset($validated['last_name'])) {
                $user->name = trim($user->first_name.' '.$user->last_name);
            }

            if (isset($validated['settings'])) {
                $incoming = $validated['settings'];
                if (array_key_exists('active_business_id', $incoming)) {
                    $activeId = (int) $incoming['active_business_id'];
                    if ($activeId > 0) {
                        $this->businessInfoService->assertUserOwnsBusiness($user, $activeId);
                    } else {
                        $incoming['active_business_id'] = null;
                    }
                }

                $current = is_array($user->settings) ? $user->settings : [];
                $merged = array_replace_recursive($current, $incoming);
                try {
                    $encoded = json_encode($merged, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    return sendResponse(false, 'Settings must be JSON-serializable.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                if (strlen($encoded) > 65535) {
                    return sendResponse(false, 'Stored settings exceed maximum size after merge.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $user->settings = $merged;
            }

            $previousImagePath = $user->image;
            $newImagePath = null;

            if ($request->hasFile('image')) {
                $newImagePath = $this->handleFileUpload(
                    $request->file('image'),
                    'users/'.$user->id.'/profile',
                    $user->first_name.' '.$user->last_name.' avatar'
                );
                $user->image = $newImagePath;
            }

            try {
                $user->save();
            } catch (Throwable $saveException) {
                if ($newImagePath !== null) {
                    $this->fileDelete($newImagePath);
                }

                throw $saveException;
            }

            if ($newImagePath !== null && is_string($previousImagePath) && $previousImagePath !== '') {
                $this->fileDelete($previousImagePath);
            }

            $user->refresh();

            return sendResponse(true, 'User settings updated successfully.', $this->payload($user));
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateEmail(UpdateUserEmailRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $this->canAccessAccountSettings($user)) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $this->authService->setUserEmailAndSendVerificationOtp($user, $request->validated('email'));
            $user->refresh();

            return sendResponse(
                true,
                'Verification code sent to your email. Enter it below to activate this address.',
                $this->payload($user),
            );
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

    public function verifyEmailOtp(VerifyUserEmailOtpRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $this->canAccessAccountSettings($user)) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $this->authService->verifyUserEmailOtp($user, $request->validated('code'));
            $user->refresh();

            return sendResponse(true, 'Email verified successfully.', $this->payload($user));
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

    public function resendEmailOtp(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $this->canAccessAccountSettings($user)) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $this->authService->resendUserEmailVerificationOtp($user);

            return sendResponse(true, 'A new verification code has been sent to your email.', null);
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

    private function canAccessAccountSettings(?User $user): bool
    {
        return $user !== null && ($user->isUser() || $user->isVendor());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        $settings = is_array($user->settings) ? $user->settings : [];

        return [
            'profile' => $this->profilePayload($user),
            'settings' => $settings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(User $user): array
    {
        return [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'wants_marketing_emails' => $user->wants_marketing_emails,
            'location' => $user->location,
            'image_path' => $user->image,
            'image_url' => $user->image_url,
            'email_verified_at' => humanDateTime($user->email_verified_at),
            'phone_verified_at' => humanDateTime($user->phone_verified_at),
            'email_verified' => $user->email_verified_at !== null,
            'email_verification_required' => $user->hasUnverifiedEmail(),
            'can_make_purchases' => $user->canMakePurchases(),
            'followers_count' => $this->userFollowService->followersCount($user->id),
            'following_count' => $this->userFollowService->followingCount($user->id),
        ];
    }
}
