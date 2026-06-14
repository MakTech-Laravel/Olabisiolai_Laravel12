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
     * @return array{user: User, business: BusinessInfo|null, created_business: bool}
     */
    public function switchToVendor(User $user): array
    {
        if ($user->isAdmin()) {
            throw ValidationException::withMessages([
                'mode' => ['Admin accounts cannot switch profile modes.'],
            ]);
        }

        $createdBusiness = false;
        $business = $this->businessInfoService->findForUser($user);

        if ($business === null) {
            $business = $this->businessInfoService->createFreeTemplateForUser($user);
            $createdBusiness = true;
        }

        $settings = is_array($user->settings) ? $user->settings : [];
        $settings['active_profile_mode'] = 'vendor';

        $user->forceFill([
            'role' => 'vendor',
            'settings' => $settings,
        ])->save();

        return [
            'user' => $user->fresh(),
            'business' => $business->fresh(['subscription', 'businessHours']),
            'created_business' => $createdBusiness,
        ];
    }

    public function switchToCustomer(User $user): User
    {
        if ($user->isAdmin()) {
            throw ValidationException::withMessages([
                'mode' => ['Admin accounts cannot switch profile modes.'],
            ]);
        }

        $settings = is_array($user->settings) ? $user->settings : [];
        $settings['active_profile_mode'] = 'customer';

        $user->forceFill([
            'role' => 'user',
            'settings' => $settings,
        ])->save();

        return $user->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function switchToVendorPayload(User $user): array
    {
        $result = $this->switchToVendor($user);

        return [
            'mode' => 'vendor',
            'created_business' => $result['created_business'],
            'business_id' => $result['business']?->id,
            'user' => UserResource::make($result['user']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function switchToCustomerPayload(User $user): array
    {
        $user = $this->switchToCustomer($user);
        $business = $this->businessInfoService->findForUser($user);

        return [
            'mode' => 'customer',
            'business_id' => $business?->id,
            'user' => UserResource::make($user),
        ];
    }
}
