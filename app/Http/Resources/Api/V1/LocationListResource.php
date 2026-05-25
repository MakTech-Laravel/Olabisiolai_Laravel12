<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lgaBoost = $this->whenLoaded('lgaBoost');

        return [
            'id' => $this->id,
            'country' => [
                'name' => $this->country_name,
                'iso_code' => $this->country_iso_code,
                'is_active' => $this->country_is_active,
                'sort_order' => $this->country_sort_order,
            ],
            'state' => [
                'name' => $this->state_name,
                'slug' => $this->state_slug,
            ],
            'city' => $this->city_name ? [
                'name' => $this->city_name,
            ] : null,
            'lga' => [
                'name' => $this->lga_name,
                'slug' => $this->lga_slug,
                'vendor_count' => (int) ($this->business_infos_count ?? $this->vendor_count),
                'google_place_id' => $this->google_place_id,
                'google_resource_name' => $this->google_resource_name,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'formatted_address' => $this->formatted_address,
                'viewport' => [
                    'north' => $this->viewport_north,
                    'south' => $this->viewport_south,
                    'east' => $this->viewport_east,
                    'west' => $this->viewport_west,
                ],
                'address_components_json' => $this->address_components_json,
                'boost' => $lgaBoost ? [
                    'enabled' => $lgaBoost->enabled,
                    'tiers' => $lgaBoost->tiers,
                    'durations' => $lgaBoost->durations,
                    'stats' => [
                        'total_slots' => $lgaBoost->total_slots,
                        'slots_sold' => $lgaBoost->slots_sold,
                        'slots_remaining' => $lgaBoost->slots_remaining,
                        'active_boosts' => $lgaBoost->active_boosts,
                        'expired_boosts' => $lgaBoost->expired_boosts,
                    ],
                ] : null,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
