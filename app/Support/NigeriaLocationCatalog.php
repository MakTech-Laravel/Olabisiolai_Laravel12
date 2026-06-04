<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Canonical Nigeria state → LGA catalog (774 LGAs across 37 states + FCT).
 *
 * @see database/data/nigeria_lgas.json
 */
class NigeriaLocationCatalog
{
    private const DATA_FILE = 'data/nigeria_lgas.json';

    private const COUNTRY_NAME = 'Nigeria';

    private const COUNTRY_ISO = 'NG';

    /**
     * Approximate coordinates per state (used when seeding LGAs before map picks).
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const STATE_COORDINATES = [
        'Abia' => [5.4527, 7.5248],
        'Adamawa' => [9.3273, 12.3984],
        'Akwa Ibom' => [5.0379, 7.9129],
        'Anambra' => [6.2209, 7.0720],
        'Bauchi' => [10.3158, 9.8442],
        'Bayelsa' => [4.9267, 6.2676],
        'Benue' => [7.7319, 8.5005],
        'Borno' => [11.8333, 13.1500],
        'Cross River' => [5.8702, 8.5988],
        'Delta' => [5.7040, 5.9339],
        'Ebonyi' => [6.2649, 8.0137],
        'Edo' => [6.3350, 5.6037],
        'Ekiti' => [7.6253, 5.2209],
        'Enugu' => [6.5244, 7.5086],
        'FCT' => [9.0765, 7.3986],
        'Gombe' => [10.2897, 11.1670],
        'Imo' => [5.4831, 7.0331],
        'Jigawa' => [12.2280, 9.5616],
        'Kaduna' => [10.5105, 7.4165],
        'Kano' => [12.0022, 8.5920],
        'Katsina' => [12.9883, 7.6009],
        'Kebbi' => [12.4500, 4.1994],
        'Kogi' => [7.8006, 6.7393],
        'Kwara' => [8.9669, 4.3874],
        'Lagos' => [6.5244, 3.3792],
        'Nasarawa' => [8.4904, 7.9441],
        'Niger' => [9.6000, 6.5500],
        'Ogun' => [6.9059, 3.3841],
        'Ondo' => [7.2507, 5.2103],
        'Osun' => [7.5629, 4.5200],
        'Oyo' => [7.3775, 3.9470],
        'Plateau' => [9.8965, 8.8583],
        'Rivers' => [4.8156, 7.0498],
        'Sokoto' => [13.0059, 5.2476],
        'Taraba' => [7.9994, 10.7739],
        'Yobe' => [11.7480, 11.9660],
        'Zamfara' => [12.1700, 6.6600],
    ];

    /** @var array<string, list<string>>|null */
    private ?array $stateLgas = null;

    /**
     * @return array<string, list<string>> state name => LGA names
     */
    public function stateLgas(): array
    {
        if ($this->stateLgas !== null) {
            return $this->stateLgas;
        }

        $path = database_path(self::DATA_FILE);
        $raw = json_decode((string) file_get_contents($path), true);

        if (! is_array($raw)) {
            throw new \RuntimeException('Invalid Nigeria LGA catalog at: ' . $path);
        }

        $normalized = [];
        foreach ($raw as $state => $lgas) {
            if (! is_array($lgas)) {
                continue;
            }
            $stateName = $this->normalizeStateName((string) $state);
            $normalized[$stateName] = array_values(array_map('strval', $lgas));
        }

        ksort($normalized);
        $this->stateLgas = $normalized;

        return $this->stateLgas;
    }

    /**
     * Shape used by LocationCatalogService (country → state → cities/LGAs).
     *
     * @return array<string, array<string, list<string>>>
     */
    public function businessLocationsConfig(): array
    {
        return [self::COUNTRY_NAME => $this->stateLgas()];
    }

    /**
     * Flat list of rows ready for locations table seeding.
     *
     * @return list<array<string, mixed>>
     */
    public function locationRows(): array
    {
        $rows = [];
        $now = now();

        foreach ($this->stateLgas() as $stateName => $lgas) {
            [$latitude, $longitude] = $this->coordinatesForState($stateName);
            $stateSlug = Str::slug($stateName);

            foreach ($lgas as $lgaName) {
                $lgaSlug = $this->lgaSlug($stateName, $lgaName);
                $rows[] = [
                    'country_name' => self::COUNTRY_NAME,
                    'country_iso_code' => self::COUNTRY_ISO,
                    'country_is_active' => true,
                    'country_sort_order' => 1,
                    'state_name' => $stateName,
                    'state_slug' => $stateSlug,
                    'city_name' => $lgaName,
                    'city_slug' => Str::slug($lgaName),
                    'lga_name' => $lgaName,
                    'lga_slug' => $lgaSlug,
                    'vendor_count' => 0,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'formatted_address' => "{$lgaName}, {$stateName}, " . self::COUNTRY_NAME,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows;
    }

    public function lgaSlug(string $stateName, string $lgaName): string
    {
        return Str::slug($stateName) . '-' . Str::slug($lgaName);
    }

    public function normalizeStateName(string $state): string
    {
        return match (strtolower(trim($state))) {
            'fct', 'federal capital territory', 'abuja' => 'FCT',
            default => trim($state),
        };
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function coordinatesForState(string $stateName): array
    {
        $coords = self::STATE_COORDINATES[$stateName] ?? [9.0820, 8.6753];

        return [(float) $coords[0], (float) $coords[1]];
    }

    public function totalLgaCount(): int
    {
        return array_sum(array_map('count', $this->stateLgas()));
    }
}
