<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'subcategories',
        'icon',
    ];

    protected function casts(): array
    {
        return [
            'subcategories' => 'array',
        ];
    }

    /**
     * @return HasMany<BusinessInfo, $this>
     */
    public function businessInfos(): HasMany
    {
        return $this->hasMany(BusinessInfo::class);
    }

    /**
     * Categories that have at least one business, highest count first.
     * Unused categories (no businesses) are skipped.
     *
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeOrderByHigherBusinessCount(Builder $query): Builder
    {
        return $query
            ->orderByDesc(
                BusinessInfo::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('business_info.category_id', 'categories.id')
            );
    }
    
    /**
     * Public URL for the category icon. Falls back to the default grid SVG when
     * the stored icon path is null or empty.
     */
    public function getIconUrlAttribute(): string
    {
        $default = asset('images/categories/layout-grid.svg');

        return public_media_url($this->icon, $default) ?? $default;
    }
}
