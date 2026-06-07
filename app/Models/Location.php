<?php

namespace App\Models;

use App\Support\NigeriaLocationCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Location extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'country_name',
        'country_iso_code',
        'country_is_active',
        'country_sort_order',
        'state_name',
        'state_slug',
        'city_name',
        'lga_name',
        'lga_slug',
        'vendor_count',
        'google_place_id',
        'google_resource_name',
        'latitude',
        'longitude',
        'formatted_address',
        'viewport_north',
        'viewport_south',
        'viewport_east',
        'viewport_west',
        'address_components_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'country_is_active' => 'boolean',
            'country_sort_order' => 'integer',
            'vendor_count' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'viewport_north' => 'decimal:7',
            'viewport_south' => 'decimal:7',
            'viewport_east' => 'decimal:7',
            'viewport_west' => 'decimal:7',
            'address_components_json' => 'array',
        ];
    }

    /**
     * @return HasOne<LgaBoost, $this>
     */
    public function lgaBoost(): HasOne
    {
        return $this->hasOne(LgaBoost::class);
    }

    /**
     * @return HasMany<BusinessInfo, $this>
     */
    public function businessInfos(): HasMany
    {
        return $this->hasMany(BusinessInfo::class);
    }

    /**
     * Get the full location name as a formatted string.
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->lga_name,
            $this->city_name,
            $this->state_name,
            $this->country_name,
        ]);

        return implode(', ', $parts);
    }

    public function displayFormattedAddress(): string
    {
        return filled($this->formatted_address) ? (string) $this->formatted_address : $this->full_name;
    }

    public function resolvedLatitude(): ?float
    {
        if ($this->latitude !== null) {
            return (float) $this->latitude;
        }

        if (! filled($this->state_name)) {
            return null;
        }

        [$latitude] = app(NigeriaLocationCatalog::class)->coordinatesForState((string) $this->state_name);

        return $latitude;
    }

    public function resolvedLongitude(): ?float
    {
        if ($this->longitude !== null) {
            return (float) $this->longitude;
        }

        if (! filled($this->state_name)) {
            return null;
        }

        [, $longitude] = app(NigeriaLocationCatalog::class)->coordinatesForState((string) $this->state_name);

        return $longitude;
    }

    /**
     * Scope to query by country.
     */
    public function scopeByCountry($query, string $country)
    {
        return $query->where('country_name', $country);
    }

    /**
     * Scope to query by state.
     */
    public function scopeByState($query, string $state)
    {
        return $query->where('state_name', $state);
    }

    /**
     * Scope to query by LGA.
     */
    public function scopeByLga($query, string $lga)
    {
        return $query->where('lga_name', $lga);
    }
}
