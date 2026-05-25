<?php

namespace App\Services;

use App\Enums\PaymentPurpose;
use App\Enums\PricingPackageType;
use App\Models\PricingPackage;
use Illuminate\Support\Collection;
use RuntimeException;

class PricingPackageService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function verificationPackages(): array
    {
        return $this->activePackages(PricingPackageType::Verification)
            ->map(fn(PricingPackage $package): array => $package->toPackageArray())
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function subscriptionPackages(): array
    {
        return $this->activePackages(PricingPackageType::Subscription)
            ->map(fn(PricingPackage $package): array => $package->toPackageArray())
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function verificationPackageKeys(): array
    {
        return $this->activePackages(PricingPackageType::Verification)
            ->pluck('package_key')
            ->all();
    }

    public function verificationCurrency(): string
    {
        return $this->activePackages(PricingPackageType::Verification)->value('currency')
            ?? config('verification.currency', 'NGN');
    }

    public function subscriptionCurrency(): string
    {
        return $this->activePackages(PricingPackageType::Subscription)->value('currency')
            ?? config('subscription.currency', 'NGN');
    }

    public function boostCurrency(): string
    {
        return config('boost.currency', config('subscription.currency', 'NGN'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPackage(PaymentPurpose $purpose, string $packageId): ?array
    {
        if ($purpose === PaymentPurpose::Boost) {
            return null;
        }

        $type = $purpose === PaymentPurpose::Verification
            ? PricingPackageType::Verification
            : PricingPackageType::Subscription;

        $package = PricingPackage::query()
            ->where('type', $type)
            ->where('package_key', $packageId)
            ->where('is_active', true)
            ->first();

        if ($package !== null) {
            return $package->toPackageArray();
        }

        return $this->findPackageFromConfig($purpose, $packageId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForAdmin(): array
    {
        return PricingPackage::query()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get()
            ->map(fn(PricingPackage $package): array => [
                'id' => $package->id,
                'package_key' => $package->package_key,
                'type' => $package->type->value,
                'title' => $package->title,
                'amount' => $package->amount,
                'currency' => $package->currency,
                'description' => $package->description,
                'perks' => $package->perks ?? [],
                'is_active' => $package->is_active,
                'sort_order' => $package->sort_order,
            ])
            ->all();
    }

    /**
     * @param  list<array{package_key: string, title?: string, amount: int, description?: string, perks?: list<string>, is_active?: bool, sort_order?: int}>  $packages
     */
    public function syncVerificationPackages(array $packages): void
    {
        $this->syncPackages(PricingPackageType::Verification, $packages);
    }

    /**
     * @param  list<array{package_key: string, title?: string, amount: int, description?: string, perks?: list<string>, is_active?: bool, sort_order?: int}>  $packages
     */
    public function syncSubscriptionPackages(array $packages): void
    {
        $this->syncPackages(PricingPackageType::Subscription, $packages);
    }

    /**
     * @param  list<array{package_key: string, title?: string, amount: int, description?: string, perks?: list<string>, is_active?: bool, sort_order?: int}>  $packages
     */
    private function syncPackages(PricingPackageType $type, array $packages): void
    {
        if ($packages === []) {
            throw new RuntimeException('At least one package is required.');
        }

        $keys = [];

        foreach ($packages as $index => $payload) {
            $key = (string) ($payload['package_key'] ?? '');

            if ($key === '') {
                throw new RuntimeException('Each package must include a package_key.');
            }

            $amount = (int) ($payload['amount'] ?? 0);

            if ($amount <= 0) {
                throw new RuntimeException("Package [{$key}] must have a positive amount.");
            }

            $keys[] = $key;

            PricingPackage::query()->updateOrCreate(
                [
                    'package_key' => $key,
                    'type' => $type,
                ],
                [
                    'title' => (string) ($payload['title'] ?? $key),
                    'amount' => $amount,
                    'currency' => $type === PricingPackageType::Verification
                        ? config('verification.currency', 'NGN')
                        : config('subscription.currency', 'NGN'),
                    'description' => (string) ($payload['description'] ?? ''),
                    'perks' => $payload['perks'] ?? [],
                    'is_active' => (bool) ($payload['is_active'] ?? true),
                    'sort_order' => (int) ($payload['sort_order'] ?? $index + 1),
                ],
            );
        }

        PricingPackage::query()
            ->where('type', $type)
            ->whereNotIn('package_key', $keys)
            ->update(['is_active' => false]);
    }

    /**
     * @return Collection<int, PricingPackage>
     */
    private function activePackages(PricingPackageType $type): Collection
    {
        $packages = PricingPackage::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($packages->isNotEmpty()) {
            return $packages;
        }

        return $this->seedFromConfig($type);
    }

    /**
     * @return Collection<int, PricingPackage>
     */
    private function seedFromConfig(PricingPackageType $type): Collection
    {
        (new \Database\Seeders\PricingPackageSeeder)->run();

        return PricingPackage::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPackageFromConfig(PaymentPurpose $purpose, string $packageId): ?array
    {
        $configPackages = $purpose === PaymentPurpose::Verification
            ? config('verification.packages', [])
            : config('subscription.packages', []);

        foreach ($configPackages as $package) {
            if (($package['id'] ?? null) === $packageId) {
                return $package;
            }
        }

        return null;
    }
}
