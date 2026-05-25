<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangeUserPasswordRequest;
use App\Http\Requests\Api\V1\UpdateVendorSettingsRequest;
use App\Http\Resources\Api\V1\BusinessInfoResource;
use App\Http\Traits\FileManagementTrait;
use App\Models\BusinessInfo;
use App\Models\User;
use App\Services\BusinessInfoService;
use App\Services\SubscriptionService;
use App\Services\TwoFactorAuthenticationService;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VendorSettingsController extends Controller
{
    use FileManagementTrait;

    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
        private readonly VerificationService $verificationService,
        private readonly SubscriptionService $subscriptionService,
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function show(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user('api');

        if (! $user?->isVendor()) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $user->refresh();
            $business = $this->businessInfoService->findForUser($user);
            if ($business !== null) {
                $business->load(['category', 'location', 'boost']);
            }

            return sendResponse(true, 'Vendor settings retrieved successfully.', $this->payload($user, $business));
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateVendorSettingsRequest $request): Response
    {
        /** @var User|null $user */
        $user = $request->user('api');

        if (! $user?->isVendor()) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $validated = $request->validated();
            $business = $this->businessInfoService->findForUser($user);

            if (array_key_exists('first_name', $validated) && filled($validated['first_name'])) {
                $user->first_name = $validated['first_name'];
            }
            if (array_key_exists('last_name', $validated) && filled($validated['last_name'])) {
                $user->last_name = $validated['last_name'];
            }
            if (
                (array_key_exists('first_name', $validated) && filled($validated['first_name']))
                || (array_key_exists('last_name', $validated) && filled($validated['last_name']))
            ) {
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

            $user->save();

            if ($business !== null) {
                if (array_key_exists('business_name', $validated) && filled($validated['business_name'])) {
                    $business->business_name = $validated['business_name'];
                }
                if (array_key_exists('phone', $validated) && filled($validated['phone'])) {
                    $business->phone = $validated['phone'];
                }

                $previousLogoPath = $business->logo_path;
                $newLogoPath = null;

                if ($request->hasFile('logo')) {
                    $newLogoPath = $this->handleFileUpload(
                        $request->file('logo'),
                        'businesses/' . $user->id . '/logo',
                        ($business->business_name ?? 'Business') . ' logo',
                    );
                    $business->logo_path = $newLogoPath;
                }

                $business->save();

                if ($newLogoPath !== null && is_string($previousLogoPath) && $previousLogoPath !== '') {
                    $this->fileDelete($previousLogoPath);
                }

                $business->refresh();
                $business->load(['category', 'location', 'boost']);
            }

            $user->refresh();

            return sendResponse(true, 'Vendor settings updated successfully.', $this->payload($user, $business));
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function changePassword(ChangeUserPasswordRequest $request): Response
    {
        /** @var User|null $user */
        $user = $request->user('api');

        if (! $user?->isVendor()) {
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
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user, ?BusinessInfo $business): array
    {
        $settings = is_array($user->settings) ? $user->settings : [];
        $notifications = is_array($settings['notifications'] ?? null) ? $settings['notifications'] : [];

        $verificationStatus = $business?->verification_status->value ?? 'none';
        $verificationLabel = $business !== null
            ? $this->verificationService->displayStatusLabel($business)
            : 'Not verified';
        $showsVerifiedBadge = $business !== null
            && $this->verificationService->showsVerifiedBadge($business);

        $businessPayload = $business !== null
            ? (new BusinessInfoResource($business))->resolve()
            : null;

        return [
            'profile' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'contact_name' => $user->name,
                'email' => $user->email,
                'phone' => $business?->phone ?? $user->phone,
                'business_name' => $business?->business_name,
                'logo_url' => $business !== null ? public_media_url($business->logo_path) : null,
            ],
            'business' => $businessPayload,
            'security' => [
                'two_factor_enabled' => $this->twoFactor->isEnabled($user),
            ],
            'notifications' => [
                'email' => (bool) ($notifications['email'] ?? true),
                'sms' => (bool) ($notifications['sms'] ?? false),
                'whatsapp' => (bool) ($notifications['whatsapp'] ?? true),
            ],
            'verification' => [
                'verification_status' => $verificationStatus,
                'verification_status_label' => $verificationLabel,
                'is_approved' => $showsVerifiedBadge,
                'shows_verified_badge' => $showsVerifiedBadge,
                'is_flagged' => (bool) ($business?->is_flagged ?? false),
            ],
            'subscription' => $business !== null
                ? $this->subscriptionService->subscriptionPayload($business)
                : [
                    'plan' => 'free',
                    'plan_label' => 'Free',
                    'status' => 'active',
                    'status_label' => 'Active',
                    'requires_payment' => false,
                    'can_access_features' => true,
                ],
            'settings' => $settings,
        ];
    }
}
