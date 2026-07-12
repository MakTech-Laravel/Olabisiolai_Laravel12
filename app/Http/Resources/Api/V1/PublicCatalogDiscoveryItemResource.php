<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCatalogDiscoveryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $business = $this->relationLoaded('businessInfo') ? $this->businessInfo : null;
        $location = $business?->relationLoaded('location') ? $business->location : null;
        $boost = $business?->relationLoaded('boost') ? $business->boost : null;
        $city = trim((string) ($location?->city_name ?? ''));
        $state = trim((string) ($location?->state_name ?? ''));
        $lga = trim((string) ($location?->lga_name ?? ''));
        $locationLabel = collect([$city ?: null, $lga ?: null, $state ?: null])
            ->filter()
            ->unique()
            ->implode(', ');

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'price_kobo' => $this->price_kobo,
            'price_label' => $this->price_label,
            'price_from' => (bool) $this->price_from,
            'image_url' => public_media_url($this->image_path, null),
            'sort_order' => (int) $this->sort_order,
            'business_info_id' => (int) $this->business_info_id,
            'business_name' => $business?->business_name,
            'category_id' => $business?->category_id,
            'category_name' => $business?->relationLoaded('category') ? $business->category?->name : null,
            'location_label' => $locationLabel !== '' ? $locationLabel : null,
            'city_name' => $city !== '' ? $city : null,
            'vendor_user_uuid' => $business?->relationLoaded('user') ? $business->user?->uuid : null,
            'is_boosted' => (bool) ($boost?->is_active),
            'is_premium' => true,
        ];
    }
}
