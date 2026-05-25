<?php

namespace App\Http\Resources\Api\V1;

use App\Services\BoostPurchaseService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $location = $this->resource;
        $lgaBoost = $location->lgaBoost;
        $boostService = app(BoostPurchaseService::class);
        $enrichedTiers = $lgaBoost ? $boostService->enrichTiersWithSlotAvailability($lgaBoost) : [];

        return [
            'id' => $location->id,
            'country' => [
                'name' => $location->country_name,
                'iso_code' => $location->country_iso_code,
                'is_active' => $location->country_is_active,
                'sort_order' => $location->country_sort_order,
            ],
            'state' => [
                'name' => $location->state_name,
                'slug' => $location->state_slug,
            ],
            'city' => $location->city_name ? [
                'name' => $location->city_name,
            ] : null,
            'lga' => [
                'name' => $location->lga_name,
                'slug' => $location->lga_slug,
                'vendor_count' => $location->vendor_count,
                'google_place_id' => $location->google_place_id,
                'google_resource_name' => $location->google_resource_name,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'formatted_address' => $location->formatted_address,
                'viewport' => [
                    'north' => $location->viewport_north,
                    'south' => $location->viewport_south,
                    'east' => $location->viewport_east,
                    'west' => $location->viewport_west,
                ],
                'address_components_json' => $location->address_components_json,
                'boost' => $lgaBoost ? [
                    'enabled' => $lgaBoost->enabled,
                    'tiers' => $enrichedTiers,
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
            'created_at' => $location->created_at,
            'updated_at' => $location->updated_at,
        ];
    }
}
