<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PricingPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PricingPackage $resource
 */
class PricingPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $package = $this->resource;
        $pricing = $package->effectivePricing();

        return [
            'id' => $package->id,
            'package_key' => $package->package_key,
            'title' => $package->title,
            'billing_period' => $package->billing_period?->value,
            'billing_period_label' => $package->billing_period?->label(),
            'amount' => $pricing['amount'],
            'original_price' => $pricing['original_price'],
            'discount_label' => $pricing['discount_label'],
            'promotional_text' => $pricing['promotional_text'],
            'promotion_starts_at' => $package->promotion_starts_at?->toIso8601String(),
            'promotion_ends_at' => $package->promotion_ends_at?->toIso8601String(),
            'currency' => $package->currency,
            'description' => $package->description ?? '',
            'perks' => $package->perks ?? [],
            'is_active' => $package->is_active,
            'is_recommended' => $package->is_recommended,
            'trial_eligible' => $package->trial_eligible,
            'trial_duration_days' => $package->trial_duration_days,
            'sort_order' => $package->sort_order,
        ];
    }
}
