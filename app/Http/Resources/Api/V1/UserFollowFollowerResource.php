<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserFollowFollowerResource extends JsonResource
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
                'name' => $follower->name,
                'image_url' => $follower->image_url,
            ] : null,
        ];
    }
}
