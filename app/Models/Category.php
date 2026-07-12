<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
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
}
