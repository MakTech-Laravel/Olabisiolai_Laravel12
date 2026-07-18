<?php

namespace App\Models;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Database\Factories\BusinessSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSubscription extends Model
{
    /** @use HasFactory<BusinessSubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'business_info_id',
        'pricing_package_id',
        'plan',
        'status',
        'expires_at',
        'trial_ends_at',
        'is_manual_grant',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'status' => SubscriptionStatus::class,
            'expires_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'is_manual_grant' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<BusinessInfo, $this>
     */
    public function businessInfo(): BelongsTo
    {
        return $this->belongsTo(BusinessInfo::class);
    }

    /**
     * @return BelongsTo<PricingPackage, $this>
     */
    public function pricingPackage(): BelongsTo
    {
        return $this->belongsTo(PricingPackage::class);
    }
}
