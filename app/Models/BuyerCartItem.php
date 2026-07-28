<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerCartItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'buyer_cart_id',
        'catalog_item_id',
        'quantity',
        'name',
        'unit_price_kobo',
        'original_unit_price_kobo',
        'price_display',
        'price_from',
        'image_url',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_kobo' => 'integer',
            'original_unit_price_kobo' => 'integer',
            'price_from' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<BuyerCart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(BuyerCart::class, 'buyer_cart_id');
    }

    /**
     * @return BelongsTo<BusinessCatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(BusinessCatalogItem::class, 'catalog_item_id');
    }

    public function lineTotalKobo(): ?int
    {
        if ($this->unit_price_kobo === null) {
            return null;
        }

        return (int) $this->unit_price_kobo * (int) $this->quantity;
    }
}
