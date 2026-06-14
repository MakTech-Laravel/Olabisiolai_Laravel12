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
use App\Services\UserFollowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserSettingsController extends Controller
{
    use FileManagementTrait;

    public function __construct(
        private readonly AuthService $authService,
        private readonly UserFollowService $userFollowService,
    ) {}

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
                $user->name = trim($user->first_name . ' ' . $user->last_name);
            }

            $user->save();
            $user->refresh();

            return sendResponse(true, 'Profile updated successfully.', $this->profilePayload($user));
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

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
                $user->name = trim($user->first_name . ' ' . $user->last_name);
            }

            if (isset($validated['settings'])) {
                $current = is_array($user->settings) ? $user->settings : [];
                $merged = array_replace_recursive($current, $validated['settings']);
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
                    'users/' . $user->id . '/profile',
                    $user->first_name . ' ' . $user->last_name . ' avatar'
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
