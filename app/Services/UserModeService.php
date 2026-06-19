<?php

namespace App\Services;

use App\Http\Resources\Api\V1\UserResource;
use App\Models\BusinessInfo;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserModeService
{
    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
    ) {}

    /**
     * Create (or reuse) a business page for management. Does not change account role
     * or social identity — users always interact as themselves.
     *
     * @return array{user: User, business: BusinessInfo|null, created_business: bool}
     */
    public function switchToVendor(User $user): array
    {
        if ($user->isAdmin()) {
            throw ValidationException::withMessages([
                'mode' => ['Admin accounts cannot create business pages.'],
            ]);
        }

        $createdBusiness = false;
        $business = $this->businessInfoService->findForUser($user);

        if ($business === null) {
            $business = $this->businessInfoService->createFreeTemplateForUser($user);
            $createdBusiness = true;
        }

        $settings = is_array($user->settings) ? $user->settings : [];
        $settings['active_business_id'] = $business->id;
        unset($settings['active_profile_mode']);

        $user->forceFill([
            'settings' => $settings,
        ])->save();

        return [
            'user' => $user->fresh(),
            'business' => $business->fresh(['subscription', 'businessHours']),
            'created_business' => $createdBusiness,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function switchToVendorPayload(User $user): array
    {
        $result = $this->switchToVendor($user);

        return [
            'mode' => 'business',
            'created_business' => $result['created_business'],
            'business_id' => $result['business']?->id,
            'user' => UserResource::make($result['user']),
        ];
    }
}
