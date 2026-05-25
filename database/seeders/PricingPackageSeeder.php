<?php

namespace Database\Seeders;

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

        $subscription = config('subscription.packages', []);
        $subCurrency = config('subscription.currency', 'NGN');

        foreach ($subscription as $index => $package) {
            PricingPackage::query()->updateOrCreate(
                [
                    'package_key' => (string) ($package['id'] ?? ''),
                    'type' => PricingPackageType::Subscription,
                ],
                [
                    'title' => (string) ($package['title'] ?? $package['id'] ?? 'Package'),
                    'amount' => (int) ($package['amount'] ?? 0),
                    'currency' => $subCurrency,
                    'description' => (string) ($package['description'] ?? ''),
                    'perks' => $package['perks'] ?? [],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
