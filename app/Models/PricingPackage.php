<?php

namespace App\Models;

use App\Enums\PricingPackageType;
use Illuminate\Database\Eloquent\Model;

class PricingPackage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'package_key',
        'type',
        'title',
        'amount',
        'currency',
        'description',
        'perks',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => PricingPackageType::class,
            'perks' => 'array',
            'is_active' => 'boolean',
            'amount' => 'integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPackageArray(): array
    {
        return [
            'id' => $this->package_key,
            'title' => $this->title,
            'amount' => $this->amount,
            'description' => $this->description ?? '',
            'perks' => $this->perks ?? [],
        ];
    }
}
