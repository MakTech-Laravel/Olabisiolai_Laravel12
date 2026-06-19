<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Models\UserFollow;
use App\Support\MessagingHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserFollowerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UserFollow $follow */
        $follow = $this->resource;
        /** @var User|null $follower */
        $follower = $follow->relationLoaded('follower') ? $follow->follower : null;

        return [
            'follower_user_id' => $follow->follower_id,
            'followed_at' => humanDateTime($follow->created_at),
            'user' => $follower ? [
                'id' => $follower->id,
                'uuid' => $follower->uuid,
                'personal_name' => MessagingHelper::participantPersonalName($follower),
                'name' => MessagingHelper::participantPersonalName($follower),
                'image_url' => $follower->image_url,
            ] : null,
            'owned_businesses' => $follower instanceof User
                ? MessagingHelper::ownedBusinessesSummary($follower)
                : [],
        ];
    }
}
