<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'image_path' => $this->image,
            'image_url' => $this->image_url,
            'role' => $this->role,
            'status' => $this->status,
            'wants_marketing_emails' => $this->wants_marketing_emails,
            'settings' => is_array($this->settings) ? $this->settings : [],
            'email_verified_at' => humanDateTime($this->email_verified_at),
            'phone_verified_at' => humanDateTime($this->phone_verified_at),
            'email_verified' => $this->email_verified_at !== null,
            'email_verification_required' => filled($this->email) && $this->email_verified_at === null,
            'can_make_purchases' => ! (filled($this->email) && $this->email_verified_at === null),
            'account_verified' => $this->isAccountVerified(),
            'verification_channel' => $this->registrationVerificationChannel(),
            'created_at' => humanDateTime($this->created_at),
        ];
    }
}
