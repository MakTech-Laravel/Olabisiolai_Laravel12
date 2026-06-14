<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BusinessInfo;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserFollowVendorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UserFollow $follow */
        $follow = $this->resource;
        /** @var User|null $vendorUser */
        $vendorUser = $follow->relationLoaded('following') ? $follow->following : null;
        /** @var BusinessInfo|null $business */
        $business = $vendorUser?->relationLoaded('businessInfo') ? $vendorUser->businessInfo : null;

        return [
            'following_user_id' => $follow->following_id,
            'followed_at' => humanDateTime($follow->created_at),
            'vendor' => $vendorUser ? [
                'id' => $vendorUser->id,
                'uuid' => $vendorUser->uuid,
                'name' => $vendorUser->name,
                'image_url' => $vendorUser->image_url,
            ] : null,
            'business' => $business ? [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'logo_url' => public_media_url($business->logo_path),
                'category_name' => $business->relationLoaded('category') && $business->category
                    ? $business->category->name
                    : null,
                'location' => $business->relationLoaded('location') && $business->location
                    ? $business->location->full_name
                    : null,
            ] : null,
        ];
    }
}
