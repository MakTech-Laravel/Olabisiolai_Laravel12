<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Support\MessagingHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class MessageRecipientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $businessInfoId = null;
        if (
            $user->role === 'vendor'
            && $user->relationLoaded('businessInfo')
            && $user->businessInfo !== null
        ) {
            $businessInfoId = (int) $user->businessInfo->id;
        }

        return [
            'uuid' => $user->uuid,
            'display_name' => MessagingHelper::recipientDisplayName($user),
            'subtitle' => MessagingHelper::recipientSearchSubtitle($user),
            'avatar_url' => MessagingHelper::recipientAvatarUrl($user),
            'is_verified' => MessagingHelper::isVerifiedVendor($user),
            'role' => $user->role,
            'business_info_id' => $businessInfoId > 0 ? $businessInfoId : null,
        ];
    }
}
