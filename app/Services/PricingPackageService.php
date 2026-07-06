<?php

namespace App\Services;

use App\Enums\PaymentPurpose;
use App\Enums\PricingPackageType;
use App\Models\PricingPackage;
use Database\Seeders\PricingPackageSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PricingPackageService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function verificationPackages(): array
    {
        return $this->activePackages(PricingPackageType::Verification)
            ->map(fn (PricingPackage $package): array => $package->toPackageArray())
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function subscriptionPackages(): array
    {
        return $this->activePackages(PricingPackageType::Subscription)
            ->map(fn (PricingPackage $package): array => $package->toPackageArray())
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

    public function findActiveSubscriptionPackageModel(string $packageKey): ?PricingPackage
    {
        return $this->activePackages(PricingPackageType::Subscription)
            ->firstWhere('package_key', $packageKey);
    }

    /**
     * The plan used when a checkout doesn't specify one: the recommended
     * active plan, or the first active plan by sort order.
     */
    public function defaultSubscriptionPackage(): ?PricingPackage
    {
        $packages = $this->activePackages(PricingPackageType::Subscription);

        return $packages->firstWhere('is_recommended', true) ?? $packages->first();
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
            ->map(fn (PricingPackage $package): array => [
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
     * @return Collection<int, PricingPackage>
     */
    public function subscriptionPlansForAdmin(): Collection
    {
        return PricingPackage::query()
            ->where('type', PricingPackageType::Subscription)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPlan(array $data): PricingPackage
    {
        $this->assertUniquePlanKey((string) $data['package_key']);

        $data['type'] = PricingPackageType::Subscription;
        $data['currency'] = config('subscription.currency', 'NGN');
        $data['sort_order'] = $data['sort_order'] ?? $this->nextSortOrder();

        $plan = PricingPackage::query()->create($data);

        if ($data['is_recommended'] ?? false) {
            $this->setRecommended($plan->id);
            $plan->refresh();
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePlan(int $id, array $data): PricingPackage
    {
        $plan = $this->findSubscriptionPlanOrFail($id);

        if (isset($data['package_key']) && $data['package_key'] !== $plan->package_key) {
            $this->assertUniquePlanKey((string) $data['package_key'], $id);
        }

        $plan->update($data);

        if ($data['is_recommended'] ?? false) {
            $this->setRecommended($plan->id);
            $plan->refresh();
        }

        return $plan;
    }

    public function deletePlan(int $id): void
    {
        $plan = $this->findSubscriptionPlanOrFail($id);
        $plan->delete();
    }

    public function setActive(int $id, bool $active): PricingPackage
    {
        $plan = $this->findSubscriptionPlanOrFail($id);
        $plan->update(['is_active' => $active]);

        return $plan;
    }

    public function setRecommended(int $id): PricingPackage
    {
        $plan = $this->findSubscriptionPlanOrFail($id);

        DB::transaction(function () use ($plan): void {
            PricingPackage::query()
                ->where('type', PricingPackageType::Subscription)
                ->where('id', '!=', $plan->id)
                ->update(['is_recommended' => false]);

            $plan->update(['is_recommended' => true]);
        });

        return $plan->refresh();
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                PricingPackage::query()
                    ->where('id', $id)
                    ->where('type', PricingPackageType::Subscription)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    private function findSubscriptionPlanOrFail(int $id): PricingPackage
    {
        return PricingPackage::query()
            ->where('type', PricingPackageType::Subscription)
            ->findOrFail($id);
    }

    private function assertUniquePlanKey(string $packageKey, ?int $ignoreId = null): void
    {
        $exists = PricingPackage::query()
            ->where('type', PricingPackageType::Subscription)
            ->where('package_key', $packageKey)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw new RuntimeException("A subscription plan with the key [{$packageKey}] already exists.");
        }
    }

    private function nextSortOrder(): int
    {
        return 1 + (int) PricingPackage::query()
            ->where('type', PricingPackageType::Subscription)
            ->max('sort_order');
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
        (new PricingPackageSeeder)->run();

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
