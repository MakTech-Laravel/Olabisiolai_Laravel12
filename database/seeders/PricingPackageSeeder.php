<?php

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Enums\PricingPackageType;
use App\Models\PricingPackage;
use Illuminate\Database\Seeder;

class PricingPackageSeeder extends Seeder
{
    public function run(): void
    {
        $verification = config('verification.packages', []);
        $currency = config('verification.currency', 'NGN');

        foreach ($verification as $index => $package) {
            PricingPackage::query()->updateOrCreate(
                [
                    'package_key' => (string) ($package['id'] ?? ''),
                    'type' => PricingPackageType::Verification,
                ],
                [
                    'title' => (string) ($package['title'] ?? $package['id'] ?? 'Package'),
                    'amount' => (int) ($package['amount'] ?? 0),
                    'currency' => $currency,
                    'description' => (string) ($package['description'] ?? ''),
                    'perks' => $package['perks'] ?? [],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        $subCurrency = config('subscription.currency', 'NGN');
        $premiumPerks = [
            'Up to 25 photos',
            'Full analytics dashboard',
            'Priority boost access',
            'Featured in search results',
        ];

        // Client-provided launch pricing (2026-07-06): annual plan anchored
        // against a higher "original" price, plus a lower-commitment monthly
        // option. Both offer the same 7-day free trial for verified vendors.
        $subscriptionPlans = [
            [
                'package_key' => 'premium_yearly',
                'billing_period' => BillingPeriod::Yearly,
                'title' => 'Premium',
                'amount' => 25000,
                'original_price' => 60000,
                'promotional_text' => 'Limited Launch Offer - Save ₦35,000',
                'description' => 'Annual premium subscription with full vendor features and marketplace visibility.',
                'is_recommended' => true,
                'sort_order' => 1,
            ],
            [
                'package_key' => 'premium_monthly',
                'billing_period' => BillingPeriod::Monthly,
                'title' => 'Flexible Plan',
                'amount' => 5000,
                'original_price' => null,
                'promotional_text' => null,
                'description' => 'Monthly premium subscription with full vendor features and marketplace visibility.',
                'is_recommended' => false,
                'sort_order' => 2,
            ],
        ];

        foreach ($subscriptionPlans as $plan) {
            PricingPackage::query()->updateOrCreate(
                [
                    'package_key' => $plan['package_key'],
                    'type' => PricingPackageType::Subscription,
                ],
                [
                    'billing_period' => $plan['billing_period'],
                    'title' => $plan['title'],
                    'amount' => $plan['amount'],
                    'original_price' => $plan['original_price'],
                    'promotional_text' => $plan['promotional_text'],
                    'currency' => $subCurrency,
                    'description' => $plan['description'],
                    'perks' => $premiumPerks,
                    'is_active' => true,
                    'is_recommended' => $plan['is_recommended'],
                    'trial_eligible' => true,
                    'trial_duration_days' => 7,
                    'sort_order' => $plan['sort_order'],
                ],
            );
        }
    }
}
