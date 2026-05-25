<?php

namespace Database\Factories;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\BusinessInfo;
use App\Models\BusinessSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessSubscription>
 */
class BusinessSubscriptionFactory extends Factory
{
    protected $model = BusinessSubscription::class;

    public function definition(): array
    {
        return [
            'business_info_id' => BusinessInfo::factory(),
            'plan' => SubscriptionPlan::Free,
            'status' => SubscriptionStatus::Active,
            'expires_at' => null,
        ];
    }

    public function premiumPending(): static
    {
        return $this->state(fn (): array => [
            'plan' => SubscriptionPlan::Premium,
            'status' => SubscriptionStatus::PendingPayment,
            'expires_at' => null,
        ]);
    }

    public function premiumActive(): static
    {
        return $this->state(fn (): array => [
            'plan' => SubscriptionPlan::Premium,
            'status' => SubscriptionStatus::Active,
            'expires_at' => now()->addYear(),
        ]);
    }
}
