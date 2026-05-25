<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\VerificationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->relationLoaded('business') || $this->business === null) {
            return [];
        }

        $business = $this->business;

        $coverPaths = is_array($business->cover_photo_paths) ? $business->cover_photo_paths : [];
        $coverPath = $coverPaths[0] ?? null;

        $avgRating = $business->getAttribute('avg_rating') ?? 0;
        $reviewsCount = $business->getAttribute('reviews_count') ?? 0;

        $isVerified = $business->verification_status === VerificationStatus::Approved;

        return [
            'business_info_id' => $business->id,
            'business_name' => $business->business_name,
            'category_name' => $business->category?->name,
            'location' => [
                'state' => $business->location?->state_name,
                'city' => $business->location?->city_name,
                'full_name' => $business->location?->full_name,
            ],
            'rating' => round((float) $avgRating, 1),
            'reviews_count' => (int) $reviewsCount,
            'is_verified' => $isVerified,
            'logo_url' => public_media_url($business->logo_path),
            'cover_photo_url' => $coverPath ? public_media_url($coverPath, null) : null,
            'phone' => $business->phone,
            'whatsapp' => $business->whatsapp,
            'website' => $business->website,
        ];
    }
}
