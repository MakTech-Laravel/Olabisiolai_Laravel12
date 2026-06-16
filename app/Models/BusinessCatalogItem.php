<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessCatalogItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_info_id',
        'type',
        'name',
        'description',
        'price_kobo',
        'price_label',
        'price_from',
        'image_path',
        'sort_order',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'price_kobo' => 'integer',
            'price_from' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BusinessInfo, $this>
     */
    public function businessInfo(): BelongsTo
    {
        return $this->belongsTo(BusinessInfo::class);
    }
}
