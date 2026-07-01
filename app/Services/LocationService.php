<?php

namespace App\Services;

use App\Models\BusinessInfo;
use App\Models\LgaBoost;
use App\Models\Location;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LocationService
{
    /**
     * Store a new location.
     *
     * @param  array<string, mixed>  $validated
     */
    public function storeLocation(array $validated): Location
    {
        return DB::transaction(function () use ($validated): Location {
            $locationData = $validated['location'] ?? [];
            $mapPickData = $validated['map_pick'] ?? [];
            $boostConfig = $validated['boost_config'] ?? [];

            // Extract Google API data from map_pick
            $googleData = [
                'google_place_id' => $mapPickData['placeId'] ?? $locationData['google_place_id'] ?? null,
                'google_resource_name' => $mapPickData['resourceName'] ?? $locationData['google_resource_name'] ?? null,
                'latitude' => $mapPickData['lat'] ?? $locationData['latitude'],
                'longitude' => $mapPickData['lng'] ?? $locationData['longitude'],
                'formatted_address' => $mapPickData['formattedAddress'] ?? $locationData['formatted_address'] ?? null,
                'viewport_north' => $mapPickData['viewport']['north'] ?? null,
                'viewport_south' => $mapPickData['viewport']['south'] ?? null,
                'viewport_east' => $mapPickData['viewport']['east'] ?? null,
                'viewport_west' => $mapPickData['viewport']['west'] ?? null,
                'address_components_json' => $mapPickData['addressComponents'] ?? $locationData['address_components_json'] ?? null,
            ];

            // Generate slug if not provided
            $lgaSlug = $locationData['lga_slug'] ?? strtolower(str_replace(' ', '-', $locationData['lga_name']));
            $stateSlug = $locationData['state_slug'] ?? strtolower(str_replace(' ', '-', $locationData['state_name']));

            // Create new Location
            $location = Location::create([
                'country_name' => $locationData['country_name'] ?? 'Nigeria',
                'country_iso_code' => $locationData['country_iso_code'] ?? 'NG',
                'country_is_active' => $locationData['country_is_active'] ?? true,
                'country_sort_order' => $locationData['country_sort_order'] ?? 0,
                'state_name' => $locationData['state_name'],
                'state_slug' => $stateSlug,
                'city_name' => $locationData['city_name'] ?? null,
                'lga_name' => $locationData['lga_name'],
                'lga_slug' => $lgaSlug,
                'vendor_count' => $locationData['vendor_count'] ?? 0,
                'google_place_id' => $googleData['google_place_id'],
                'google_resource_name' => $googleData['google_resource_name'],
                'latitude' => $googleData['latitude'],
                'longitude' => $googleData['longitude'],
                'formatted_address' => $googleData['formatted_address'],
                'viewport_north' => $googleData['viewport_north'],
                'viewport_south' => $googleData['viewport_south'],
                'viewport_east' => $googleData['viewport_east'],
                'viewport_west' => $googleData['viewport_west'],
                'address_components_json' => $googleData['address_components_json'],
            ]);

            // Always create boost record so admin can toggle availability later.
            $enabled = (bool) ($boostConfig['enabled'] ?? false);
            $tiers = $this->transformBoostTiers($boostConfig['tiers'] ?? []);
            $durations = $this->transformBoostDurations($boostConfig['durations'] ?? []);
            $stats = $this->computeBoostStats($tiers);

            LgaBoost::create([
                'location_id' => $location->id,
                'enabled' => $enabled,
                'tiers' => $tiers,
                'durations' => $durations,
                'total_slots' => $stats['total_slots'],
                'slots_sold' => $stats['slots_sold'],
                'slots_remaining' => $stats['slots_remaining'],
                'active_boosts' => $stats['active_boosts'],
                'expired_boosts' => $stats['expired_boosts'],
            ]);

            return $location->fresh(['lgaBoost']);
        });
    }

    /**
     * List locations with optional filters and pagination.
     *
     * @return LengthAwarePaginator<Location>
     */
    public function listLocations(?string $search = null, int $perPage = 15, ?string $filterBoost = null): LengthAwarePaginator
    {
        $query = Location::query()
            ->with('lgaBoost')
            ->withCount('businessInfos')
            ->when($search, function ($q, string $search): void {
                $q->where('lga_name', 'like', "%{$search}%")
                    ->orWhere('state_name', 'like', "%{$search}%")
                    ->orWhere('country_name', 'like', "%{$search}%")
                    ->orWhere('formatted_address', 'like', "%{$search}%");
            })
            ->when($filterBoost === 'enabled', function ($q): void {
                $q->whereHas('lgaBoost', function ($subQ): void {
                    $subQ->where('enabled', true);
                });
            })
            ->when($filterBoost === 'disabled', function ($q): void {
                $q->whereHas('lgaBoost', function ($subQ): void {
                    $subQ->where('enabled', false);
                })->orWhereDoesntHave('lgaBoost');
            })
            ->orderBy('state_name')
            ->orderBy('lga_name');

        return $query->paginate($perPage);
    }

    /**
     * Return every location row (admin hierarchy view).
     *
     * @return Collection<int, Location>
     */
    public function listAllLocations(?string $search = null, ?string $filterBoost = null): Collection
    {
        return Location::query()
            ->with('lgaBoost')
            ->withCount('businessInfos')
            ->when($search, function ($q, string $search): void {
                $q->where('lga_name', 'like', "%{$search}%")
                    ->orWhere('state_name', 'like', "%{$search}%")
                    ->orWhere('country_name', 'like', "%{$search}%")
                    ->orWhere('formatted_address', 'like', "%{$search}%");
            })
            ->when($filterBoost === 'enabled', function ($q): void {
                $q->whereHas('lgaBoost', function ($subQ): void {
                    $subQ->where('enabled', true);
                });
            })
            ->when($filterBoost === 'disabled', function ($q): void {
                $q->whereHas('lgaBoost', function ($subQ): void {
                    $subQ->where('enabled', false);
                })->orWhereDoesntHave('lgaBoost');
            })
            ->orderBy('state_name')
            ->orderBy('lga_name')
            ->get();
    }

    /**
     * Update an existing location.
     *
     * @param  array<string, mixed>  $validated
     */
    public function updateLocation(int $locationId, array $validated): Location
    {
        $location = $this->findLocation($locationId);

        return DB::transaction(function () use ($location, $validated): Location {
            $locationData = $validated['location'] ?? [];
            $mapPickData = $validated['map_pick'] ?? [];
            $boostConfig = $validated['boost_config'] ?? null;

            // Extract Google API data from map_pick
            $googleData = [
                'google_place_id' => $mapPickData['placeId'] ?? $location->google_place_id,
                'google_resource_name' => $mapPickData['resourceName'] ?? $location->google_resource_name,
                'latitude' => $mapPickData['lat'] ?? $location->latitude,
                'longitude' => $mapPickData['lng'] ?? $location->longitude,
                'formatted_address' => $mapPickData['formattedAddress'] ?? $location->formatted_address,
                'viewport_north' => $mapPickData['viewport']['north'] ?? $location->viewport_north,
                'viewport_south' => $mapPickData['viewport']['south'] ?? $location->viewport_south,
                'viewport_east' => $mapPickData['viewport']['east'] ?? $location->viewport_east,
                'viewport_west' => $mapPickData['viewport']['west'] ?? $location->viewport_west,
                'address_components_json' => $mapPickData['addressComponents'] ?? $location->address_components_json,
            ];

            // Update location
            $locationUpdate = [
                'country_name' => $locationData['country_name'] ?? $location->country_name,
                'country_iso_code' => $locationData['country_iso_code'] ?? $location->country_iso_code,
                'country_is_active' => $locationData['country_is_active'] ?? $location->country_is_active,
                'country_sort_order' => $locationData['country_sort_order'] ?? $location->country_sort_order,
                'state_name' => $locationData['state_name'] ?? $location->state_name,
                'city_name' => $locationData['city_name'] ?? $location->city_name,
                'lga_name' => $locationData['lga_name'] ?? $location->lga_name,
                'vendor_count' => $locationData['vendor_count'] ?? $location->vendor_count,
                'google_place_id' => $googleData['google_place_id'],
                'google_resource_name' => $googleData['google_resource_name'],
                'latitude' => $googleData['latitude'],
                'longitude' => $googleData['longitude'],
                'formatted_address' => $googleData['formatted_address'],
                'viewport_north' => $googleData['viewport_north'],
                'viewport_south' => $googleData['viewport_south'],
                'viewport_east' => $googleData['viewport_east'],
                'viewport_west' => $googleData['viewport_west'],
                'address_components_json' => $googleData['address_components_json'],
            ];

            // Update slugs if names changed
            if (isset($locationData['state_name']) && $locationData['state_name'] !== $location->state_name) {
                $locationUpdate['state_slug'] = strtolower(str_replace(' ', '-', $locationData['state_name']));
            }

            if (isset($locationData['lga_name']) && $locationData['lga_name'] !== $location->lga_name) {
                $locationUpdate['lga_slug'] = strtolower(str_replace(' ', '-', $locationData['lga_name']));
            }

            $location->forceFill($locationUpdate)->save();

            // Update boost availability when provided (pricing tiers are no longer managed here).
            if ($boostConfig !== null) {
                $tiers = $this->transformBoostTiers($boostConfig['tiers'] ?? []);
                $durations = $this->transformBoostDurations($boostConfig['durations'] ?? []);
                $stats = $this->computeBoostStats($tiers);

                $lgaBoost = $location->lgaBoost ?? new LgaBoost(['location_id' => $location->id]);

                $update = [
                    'enabled' => $boostConfig['enabled'] ?? $lgaBoost->enabled ?? false,
                    'total_slots' => $stats['total_slots'],
                    'slots_sold' => $lgaBoost->exists ? $lgaBoost->slots_sold : $stats['slots_sold'],
                    'slots_remaining' => $stats['total_slots'] - ($lgaBoost->exists ? $lgaBoost->slots_sold : $stats['slots_sold']),
                    'active_boosts' => $lgaBoost->exists ? $lgaBoost->active_boosts : $stats['active_boosts'],
                    'expired_boosts' => $lgaBoost->exists ? $lgaBoost->expired_boosts : $stats['expired_boosts'],
                ];

                if ($tiers !== [] || ! $lgaBoost->exists) {
                    $update['tiers'] = $tiers;
                }
                if ($durations !== [] || ! $lgaBoost->exists) {
                    $update['durations'] = $durations;
                }

                $lgaBoost->forceFill($update)->save();
            }

            return $location->fresh(['lgaBoost']);
        });
    }

    /**
     * Delete an existing location.
     */
    public function deleteLocation(int $locationId): void
    {
        $location = $this->findLocation($locationId);
        DB::transaction(function () use ($location): void {
            $location->lgaBoost()->delete();
            $location->delete();
        });
    }

    public function refreshVendorCount(int $locationId): void
    {
        if ($locationId <= 0) {
            return;
        }

        $count = BusinessInfo::query()
            ->where('location_id', $locationId)
            ->count();

        Location::query()->whereKey($locationId)->update(['vendor_count' => $count]);
    }

    public function refreshVendorCountsAfterMove(?int $previousLocationId, int $newLocationId): void
    {
        if ($previousLocationId !== null && $previousLocationId > 0 && $previousLocationId !== $newLocationId) {
            $this->refreshVendorCount($previousLocationId);
        }

        $this->refreshVendorCount($newLocationId);
    }

    /**
     * Rebuild vendor_count for every LGA row from business_info.location_id.
     */
    public function syncAllVendorCounts(): int
    {
        $updated = 0;

        Location::query()
            ->select('id')
            ->orderBy('id')
            ->each(function (Location $location) use (&$updated): void {
                $this->refreshVendorCount((int) $location->id);
                $updated++;
            });

        return $updated;
    }

    /**
     * Toggle boost status on a location.
     */
    public function toggleBoostActive(int $locationId, bool $active): Location
    {
        $location = $this->findLocation($locationId);
        $lgaBoost = $location->lgaBoost;

        if ($lgaBoost === null) {
            LgaBoost::query()->create(array_merge(
                $this->emptyLgaBoostDefaults($active),
                ['location_id' => $location->id],
            ));
        } else {
            $lgaBoost->forceFill(['enabled' => $active])->save();
        }

        return $location->fresh(['lgaBoost']);
    }

    /**
     * Default boost row for availability-only management (no tier pricing).
     *
     * @return array{
     *     enabled: bool,
     *     tiers: array<int, mixed>,
     *     durations: array<int, mixed>,
     *     total_slots: int,
     *     slots_sold: int,
     *     slots_remaining: int,
     *     active_boosts: int,
     *     expired_boosts: int
     * }
     */
    private function emptyLgaBoostDefaults(bool $enabled): array
    {
        return [
            'enabled' => $enabled,
            'tiers' => [],
            'durations' => [],
            'total_slots' => 0,
            'slots_sold' => 0,
            'slots_remaining' => 0,
            'active_boosts' => 0,
            'expired_boosts' => 0,
        ];
    }

    /**
     * Get vendors for a location.
     *
     * @return Collection<int, array{vendor_id:int, lat:float|null, lng:float|null}>
     */
    public function locationVendors(int $locationId): Collection
    {
        $location = $this->findLocation($locationId);

        // This would need to be implemented based on your vendor-location relationship
        // For now, returning an empty collection as the structure has changed
        return collect([]);
    }

    /**
     * Sync vendors for a location.
     *
     * @param  array<int, array{vendor_id:int,lat:float|null,lng:float|null}>  $vendors
     */
    public function syncLocationVendors(int $locationId, array $vendors): void
    {
        $location = $this->findLocation($locationId);
        // This would need to be implemented based on your vendor-location relationship
        // The previous VendorLga model has been removed, so this needs a new approach
    }

    /**
     * Change the active status of a location.
     */
    public function changeLocationStatus(int $locationId, bool $isActive): Location
    {
        $location = $this->findLocation($locationId);

        $location->update(['country_is_active' => $isActive]);

        return $location->fresh(['lgaBoost']);
    }

    /**
     * Find a location by ID or throw an exception.
     *
     * @param  array<int, string>  $with
     */
    private function findLocation(int $locationId, array $with = ['lgaBoost']): Location
    {
        return Location::with($with)->findOrFail($locationId);
    }

    /**
     * Transform frontend tier payloads to storage format.
     *
     * @param  array<int, array{key:string,label:string,total_slots:int,price_amount:int|float}>  $tiers
     * @return array<int, array{key:string,label:string,total_slots:int,price_amount:int|float}>
     */
    private function transformBoostTiers(array $tiers): array
    {
        return collect($tiers)->map(function (array $tier): array {
            $payload = [
                'key' => $tier['key'],
                'label' => $tier['label'],
                'total_slots' => (int) ($tier['total_slots'] ?? 0),
                'price_amount' => (int) ($tier['price_amount'] ?? 0),
            ];

            if (! empty($tier['durations']) && is_array($tier['durations'])) {
                $payload['durations'] = collect($tier['durations'])->map(function (array $duration): array {
                    return [
                        'days' => (int) $duration['days'],
                        'enabled' => (bool) $duration['enabled'],
                        'price_amount' => (int) $duration['price_amount'],
                    ];
                })->values()->all();
            }

            return $payload;
        })->values()->all();
    }

    /**
     * Transform frontend duration payloads to storage format.
     *
     * @param  array<int, array{days:int,enabled:bool,price_amount:int|float}>  $durations
     * @return array<int, array{days:int,enabled:bool,price_amount:int|float}>
     */
    private function transformBoostDurations(array $durations): array
    {
        return collect($durations)->map(function (array $duration): array {
            return [
                'days' => (int) $duration['days'],
                'enabled' => (bool) $duration['enabled'],
                'price_amount' => (int) $duration['price_amount'],
            ];
        })->values()->all();
    }

    /**
     * Compute boost stats from tiers.
     *
     * @param  array<int, array{key:string,label:string,total_slots:int,price_amount:int|float}>|null  $tiers
     * @return array{total_slots:int,slots_sold:int,slots_remaining:int,active_boosts:int,expired_boosts:int}
     */
    private function computeBoostStats(?array $tiers): array
    {
        return [
            'total_slots' => 0,
            'slots_sold' => 0,
            'slots_remaining' => 0,
            'active_boosts' => 0,
            'expired_boosts' => 0,
        ];
    }
}
