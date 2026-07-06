<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\PricingPackageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingPackage extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'package_key',
        'type',
        'billing_period',
        'title',
        'amount',
        'original_price',
        'promotional_text',
        'promotion_starts_at',
        'promotion_ends_at',
        'currency',
        'description',
        'perks',
        'is_active',
        'is_recommended',
        'trial_eligible',
        'trial_duration_days',
        'sort_order',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => PricingPackageType::class,
            'billing_period' => BillingPeriod::class,
            'perks' => 'array',
            'is_active' => 'boolean',
            'is_recommended' => 'boolean',
            'trial_eligible' => 'boolean',
            'amount' => 'integer',
            'original_price' => 'integer',
            'trial_duration_days' => 'integer',
            'promotion_starts_at' => 'datetime',
            'promotion_ends_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPackageArray(): array
    {
        $pricing = $this->effectivePricing();

        return [
            'id' => $this->package_key,
            'title' => $this->title,
            'amount' => $pricing['amount'],
            'original_price' => $pricing['original_price'],
            'discount_label' => $pricing['discount_label'],
            'promotional_text' => $pricing['promotional_text'],
            'billing_period' => $this->billing_period?->value,
            'description' => $this->description ?? '',
            'perks' => $this->perks ?? [],
            'is_recommended' => $this->is_recommended,
            'trial_eligible' => $this->trial_eligible,
            'trial_duration_days' => $this->trial_duration_days,
        ];
    }

    /**
     * Resolve the price actually in effect right now, applying the promotional
     * price/label only while `now()` falls within the promotion window. The
     * discount label (e.g. "Save 58%") is always derived from
     * original_price/amount rather than typed in by the admin.
     *
     * @return array{amount: int, original_price: int|null, discount_label: string|null, promotional_text: string|null}
     */
    public function effectivePricing(): array
    {
        $promotionActive = $this->original_price !== null
            && (! $this->promotion_starts_at || $this->promotion_starts_at->isPast())
            && (! $this->promotion_ends_at || $this->promotion_ends_at->isFuture());

        $discountLabel = null;
        if ($promotionActive && $this->original_price > 0 && $this->original_price > $this->amount) {
            $percentOff = (int) round((($this->original_price - $this->amount) / $this->original_price) * 100);
            $discountLabel = $percentOff > 0 ? "Save {$percentOff}%" : null;
        }

        return [
            'amount' => $this->amount,
            'original_price' => $promotionActive ? $this->original_price : null,
            'discount_label' => $discountLabel,
            'promotional_text' => $promotionActive ? $this->promotional_text : null,
        ];
    }
}
