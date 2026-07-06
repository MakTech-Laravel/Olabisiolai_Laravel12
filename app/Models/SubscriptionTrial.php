<?php

namespace App\Models;

use App\Enums\TrialEndedReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionTrial extends Model
{
    protected $fillable = [
        'business_info_id',
        'pricing_package_id',
        'started_at',
        'ends_at',
        'ended_reason',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'ended_reason' => TrialEndedReason::class,
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
