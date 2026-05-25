<?php

namespace App\Services;

/**
 * Resolves static business locations from config (no database).
 */
class LocationCatalogService
{
    /**
     * @return array<string, array<string, list<string>>>
     */
    public function hierarchy(): array
    {
        return config('business_locations', []);
    }

    /**
     * Nested structure for API consumers (dropdowns).
     *
     * @return list<array{location: string, states: list<array{state: string, cities: list<string>}>}>
     */
    public function treeForApi(): array
    {
        $tree = [];

        foreach ($this->hierarchy() as $location => $states) {
            $stateNodes = [];
            foreach ($states as $state => $cities) {
                $stateNodes[] = [
                    'state' => $state,
                    'cities' => array_values($cities),
                ];
            }
            $tree[] = [
                'location' => $location,
                'states' => $stateNodes,
            ];
        }

        return $tree;
    }

    public function isValidCombination(string $location, string $state, string $city): bool
    {
        $hierarchy = $this->hierarchy();

        if (! isset($hierarchy[$location][$state])) {
            return false;
        }

        return in_array($city, $hierarchy[$location][$state], true);
    }

    /**
     * @return list<string>
     */
    public function locationNames(): array
    {
        return array_keys($this->hierarchy());
    }
}
