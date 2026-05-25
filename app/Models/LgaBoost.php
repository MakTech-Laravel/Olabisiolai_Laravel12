<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LgaBoost extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'location_id',
        'enabled',
        'tiers',
        'durations',
        'total_slots',
        'slots_sold',
        'slots_remaining',
        'active_boosts',
        'expired_boosts',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'tiers' => 'array',
            'durations' => 'array',
            'total_slots' => 'integer',
            'slots_sold' => 'integer',
            'slots_remaining' => 'integer',
            'active_boosts' => 'integer',
            'expired_boosts' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Check if there are available slots for boosting.
     */
    public function hasAvailableSlots(): bool
    {
        return $this->slots_remaining > 0;
    }

    /**
     * Get the occupancy percentage.
     */
    public function getOccupancyPercentageAttribute(): float
    {
        if ($this->total_slots === 0) {
            return 0;
        }

        return ($this->slots_sold / $this->total_slots) * 100;
    }

    /**
     * Scope to query only enabled boosts.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to query boosts with available slots.
     */
    public function scopeWithAvailableSlots($query)
    {
        return $query->where('slots_remaining', '>', 0);
    }
}
