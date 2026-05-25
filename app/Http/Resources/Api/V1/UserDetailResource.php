<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin user detail view — account fields plus optional vendor business profile.
 */
class UserDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = (new UserResource($this->resource))->resolve($request);

        return array_merge($base, [
            'uuid' => $this->uuid,
            'user_profile' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'location' => $this->location,
                'image_path' => $this->image,
                'image_url' => storage_url($this->image),
                'wants_marketing_emails' => (bool) $this->wants_marketing_emails,
                'settings' => is_array($this->settings) ? $this->settings : [],
                'email_verified_at' => humanDateTime($this->email_verified_at),
                'created_at' => humanDateTime($this->created_at),
                'updated_at' => humanDateTime($this->updated_at),
            ],
            'vendor_profile' => $this->when(
                $this->relationLoaded('businessInfo') && $this->businessInfo !== null,
                fn () => (new BusinessInfoResource($this->businessInfo))->resolve($request),
            ),
        ]);
    }
}
