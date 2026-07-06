<?php

namespace App\Services;

use App\Enums\BoostPurchaseRequestStatus;
use App\Enums\BusinessStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Enums\TrialEndedReason;
use App\Models\BusinessInfo;
use App\Models\BusinessSubscription;
use App\Models\Payment;
use App\Models\PricingPackage;
use App\Models\SubscriptionTrial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SubscriptionService
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PricingPackageService $pricingPackageService,
        private readonly BoostPurchaseService $boostPurchaseService,
        private readonly WalletService $walletService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function packages(): array
    {
        return $this->pricingPackageService->subscriptionPackages();
    }

    public function subscriptionDurationDays(): int
    {
        return max(1, (int) config('subscription.duration_days', 365));
    }

    public function syncExpiredSubscription(BusinessInfo $business): BusinessInfo
    {
        $subscription = $this->subscriptionRecord($business);

        if (
            $subscription->plan === SubscriptionPlan::Premium
            && $subscription->status === SubscriptionStatus::Active
            && $this->isSubscriptionExpired($subscription)
        ) {
            $subscription->update([
                'status' => SubscriptionStatus::Expired,
            ]);

            $business->update([
                'business_status' => BusinessStatus::Inactive,
            ]);

            return $business->fresh(['subscription']);
        }

        if (
            $subscription->status === SubscriptionStatus::Trialing
            && $subscription->trial_ends_at !== null
            && $subscription->trial_ends_at->isPast()
        ) {
            return $this->downgradeToFree($business, TrialEndedReason::Expired);
        }

        return $business;
    }

    public function isSubscriptionExpired(BusinessSubscription $subscription): bool
    {
        return $subscription->expires_at !== null
            && $subscription->expires_at->isPast();
    }

    public function hasActivePremium(BusinessInfo $business): bool
    {
        $business = $this->syncExpiredSubscription($business);
        $subscription = $this->subscriptionRecord($business);

        return $subscription->plan === SubscriptionPlan::Premium
            && in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true)
            && ! $this->isSubscriptionExpired($subscription);
    }

    public function freePhotoLimit(): int
    {
        return max(1, (int) config('subscription.photo_limits.free', 5));
    }

    public function premiumPhotoLimit(): int
    {
        return max($this->freePhotoLimit(), (int) config('subscription.photo_limits.premium', 20));
    }

    public function maxCoverPhotos(BusinessInfo $business): int
    {
        return $this->hasActivePremium($business)
            ? $this->premiumPhotoLimit()
            : $this->freePhotoLimit();
    }

    public function isBusinessVerified(BusinessInfo $business): bool
    {
        return app(VerificationService::class)->showsVerifiedBadge($business);
    }

    public function canUseBoost(BusinessInfo $business): bool
    {
        return $this->hasActivePremium($business) && $this->isBusinessVerified($business);
    }

    public function requiresPayment(BusinessInfo $business): bool
    {
        $business = $this->syncExpiredSubscription($business);
        $subscription = $this->subscriptionRecord($business);

        if (
            $subscription->plan === SubscriptionPlan::Premium
            && $subscription->status === SubscriptionStatus::PendingPayment
        ) {
            return true;
        }

        if (
            $subscription->plan === SubscriptionPlan::Premium
            && $subscription->status === SubscriptionStatus::Expired
        ) {
            return true;
        }

        return false;
    }

    public function canPayForPremium(BusinessInfo $business): bool
    {
        if ($this->hasActivePremium($business)) {
            return false;
        }

        if ($this->requiresPayment($business)) {
            return true;
        }

        $subscription = $this->subscriptionRecord($business);

        return $subscription->plan === SubscriptionPlan::Free
            && $subscription->status === SubscriptionStatus::Active;
    }

    public function canAccessVendorFeatures(BusinessInfo $business): bool
    {
        if ($this->requiresPayment($business)) {
            return false;
        }

        $subscription = $this->subscriptionRecord($business);

        if ($subscription->plan === SubscriptionPlan::Premium) {
            return $this->hasActivePremium($business);
        }

        return $subscription->status === SubscriptionStatus::Active;
    }

    public function preparePremiumCheckout(BusinessInfo $business): BusinessInfo
    {
        if ($this->hasActivePremium($business)) {
            throw new RuntimeException('Premium subscription is already active.');
        }

        $subscription = $this->subscriptionRecord($business);

        if ($subscription->plan === SubscriptionPlan::Free) {
            $subscription->update([
                'plan' => SubscriptionPlan::Premium,
                'status' => SubscriptionStatus::PendingPayment,
            ]);

            $business->update([
                'business_status' => BusinessStatus::Inactive,
            ]);

            return $business->fresh(['subscription']);
        }

        if (! $this->requiresPayment($business)) {
            throw new RuntimeException('This business is not eligible for premium checkout.');
        }

        return $business;
    }

    /**
     * @return array{
     *     subscription_payment: Payment,
     *     boost_payment: Payment|null,
     *     total_amount: float,
     *     currency: string,
     * }
     */
    public function initPremiumPayment(
        User $vendor,
        BusinessInfo $business,
        ?string $boostTierKey = null,
        ?int $boostDurationDays = null,
        ?PaymentGateway $gateway = null,
        ?float $boostBudgetAmount = null,
        ?string $packageKey = null,
    ): array {
        $business = $this->preparePremiumCheckout($business);
        $checkoutGroupId = (string) Str::uuid();

        $package = $packageKey !== null
            ? $this->pricingPackageService->findActiveSubscriptionPackageModel($packageKey)
            : $this->pricingPackageService->defaultSubscriptionPackage();

        if ($package === null) {
            throw new RuntimeException('No active subscription plan is available.');
        }

        $subscriptionPayment = $this->paymentService->initPayment(
            $vendor,
            $business,
            PaymentPurpose::Subscription,
            $package->package_key,
            0,
            [
                'checkout_group_id' => $checkoutGroupId,
                'line_item' => 'subscription',
                'pricing_package_id' => $package->id,
            ],
            $gateway,
        );

        $boostPayment = null;

        if ($boostTierKey !== null && $boostDurationDays !== null) {
            $business->loadMissing('location.lgaBoost');
            if ($business->location === null) {
                throw new RuntimeException('Select a business location before adding a boost.');
            }

            if ($this->boostPurchaseService->isDynamicTier($boostTierKey)) {
                if ($boostBudgetAmount === null) {
                    throw new RuntimeException('Boost budget is required.');
                }

                $this->boostPurchaseService->assertDynamicBoost($boostDurationDays, $boostBudgetAmount);
                $pricing = $this->boostPurchaseService->resolveDynamicBoostPrice($boostBudgetAmount, $boostDurationDays);
                $boostTierKey = $this->boostPurchaseService->dynamicTierKey();
            } else {
                $lgaBoost = $this->boostPurchaseService->assertBoostAvailableForLocation(
                    $business->location,
                    $boostTierKey,
                    $boostDurationDays,
                );
                $pricing = $this->boostPurchaseService->resolveTierDurationPrice(
                    $lgaBoost,
                    $boostTierKey,
                    $boostDurationDays,
                );
            }

            $boostPayment = $this->paymentService->initBoostPayment(
                $vendor,
                $business,
                $pricing['amount'],
                [
                    'checkout_group_id' => $checkoutGroupId,
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'boost_tier_key' => $boostTierKey,
                    'boost_tier_label' => $pricing['tier_label'],
                    'boost_duration_days' => $boostDurationDays,
                    'location_label' => $business->location->full_name,
                    'boost_model' => $this->boostPurchaseService->isDynamicTier($boostTierKey) ? 'dynamic' : 'slot_tier',
                    'boost_budget_amount' => $boostBudgetAmount,
                    'boost_daily_budget' => $pricing['daily_budget'],
                    'boost_total_amount' => $pricing['amount'],
                ],
                $boostTierKey,
                $gateway,
            );

            $this->boostPurchaseService->createRequest(
                $vendor,
                $business,
                $boostTierKey,
                $boostDurationDays,
                BoostPurchaseRequestStatus::PendingPayment,
                $boostPayment,
                null,
                null,
                (int) $business->location_id,
                $boostBudgetAmount,
            );

            $subscriptionPayment->update([
                'metadata' => array_merge(
                    is_array($subscriptionPayment->metadata) ? $subscriptionPayment->metadata : [],
                    [
                        'boost_payment_id' => $boostPayment->id,
                        'checkout_group_id' => $checkoutGroupId,
                    ],
                ),
            ]);
            $subscriptionPayment = $subscriptionPayment->fresh();
        }

        $totalAmount = (float) $subscriptionPayment->amount
            + (float) ($boostPayment?->amount ?? 0);

        return [
            'subscription_payment' => $subscriptionPayment,
            'boost_payment' => $boostPayment,
            'total_amount' => $totalAmount,
            'currency' => $subscriptionPayment->currency,
        ];
    }

    /**
     * @return array{business: BusinessInfo, wallet_balance: float}
     */
    public function payPremiumFromWallet(
        User $vendor,
        BusinessInfo $business,
        ?string $boostTierKey = null,
        ?int $boostDurationDays = null,
        ?float $boostBudgetAmount = null,
        ?string $packageKey = null,
    ): array {
        $checkout = $this->initPremiumPayment(
            $vendor,
            $business,
            $boostTierKey,
            $boostDurationDays,
            null,
            $boostBudgetAmount,
            $packageKey,
        );

        $total = (float) $checkout['total_amount'];
        $this->walletService->debit($vendor, $total, 'Premium checkout', $checkout['subscription_payment']->tx_ref);

        /** @var Payment $subscriptionPayment */
        $subscriptionPayment = $checkout['subscription_payment'];
        $subscriptionPayment->update([
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
            'gateway_transaction_id' => 'wallet_'.$subscriptionPayment->tx_ref,
            'gateway' => PaymentGateway::Wallet,
        ]);

        $activatedBusiness = $this->activatePremiumAfterPayment($subscriptionPayment, $vendor);

        $boostPayment = $checkout['boost_payment'];
        if ($boostPayment instanceof Payment) {
            $boostPayment->update([
                'status' => PaymentStatus::Completed,
                'paid_at' => now(),
                'gateway_transaction_id' => 'wallet_'.$boostPayment->tx_ref,
                'gateway' => PaymentGateway::Wallet,
            ]);
            $this->paymentService->consumePayment($boostPayment);
        }

        $wallet = $this->walletService->getOrCreateWallet($vendor);

        return [
            'business' => $activatedBusiness,
            'wallet_balance' => (float) $wallet->balance,
        ];
    }

    public function findResumableSubscriptionPayment(User $vendor, BusinessInfo $business): ?Payment
    {
        return Payment::query()
            ->where('user_id', $vendor->id)
            ->where('business_info_id', $business->id)
            ->where('purpose', PaymentPurpose::Subscription)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();
    }

    public function activatePremiumAfterPayment(Payment $payment, User $vendor): BusinessInfo
    {
        if ($payment->purpose !== PaymentPurpose::Subscription) {
            throw new RuntimeException('Invalid payment for premium subscription.');
        }

        $business = $payment->businessInfo;

        if ($business === null) {
            throw new RuntimeException('Payment is not linked to a business profile.');
        }

        if ($business->user_id !== $vendor->id) {
            throw new RuntimeException('Payment does not belong to this vendor.');
        }

        $business = $this->syncExpiredSubscription($business);

        if ($this->hasActivePremium($business)) {
            return $business->fresh(['subscription']);
        }

        if ($payment->is_consumed) {
            return $this->repairPremiumActivationFromConsumedPayment($payment, $business);
        }

        if (! $payment->isConsumable()) {
            throw new RuntimeException('Complete payment before activating premium.');
        }

        $expiresAt = $this->resolveExpiryForPayment($payment);

        return DB::transaction(function () use ($payment, $business, $expiresAt): BusinessInfo {
            $this->paymentService->consumePayment($payment);

            return $this->applyPremiumActivation($payment, $business, $expiresAt);
        });
    }

    private function repairPremiumActivationFromConsumedPayment(Payment $payment, BusinessInfo $business): BusinessInfo
    {
        $expiresAt = $this->resolveExpiryForPayment($payment);

        return DB::transaction(function () use ($payment, $business, $expiresAt): BusinessInfo {
            return $this->applyPremiumActivation($payment, $business, $expiresAt);
        });
    }

    /**
     * Resolve how long the purchased plan lasts from the package linked to the
     * payment's metadata, falling back to the global default for older payments
     * created before per-package billing periods existed.
     */
    private function resolveExpiryForPayment(Payment $payment): ?\DateTimeInterface
    {
        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $packageId = isset($meta['pricing_package_id']) ? (int) $meta['pricing_package_id'] : null;
        $package = $packageId ? PricingPackage::query()->find($packageId) : null;
        $durationDays = $package?->billing_period?->durationDays();

        if ($package !== null && $package->billing_period !== null && $durationDays === null) {
            return null;
        }

        return now()->addDays($durationDays ?? $this->subscriptionDurationDays());
    }

    private function applyPremiumActivation(Payment $payment, BusinessInfo $business, ?\DateTimeInterface $expiresAt): BusinessInfo
    {
        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $packageId = isset($meta['pricing_package_id']) ? (int) $meta['pricing_package_id'] : null;

        $subscription = $this->subscriptionRecord($business);
        $subscription->update([
            'plan' => SubscriptionPlan::Premium,
            'status' => SubscriptionStatus::Active,
            'expires_at' => $expiresAt,
            'pricing_package_id' => $packageId,
            'trial_ends_at' => null,
        ]);

        $business->update([
            'business_status' => BusinessStatus::Active,
        ]);

        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $boostPaymentId = isset($meta['boost_payment_id']) ? (int) $meta['boost_payment_id'] : 0;

        if ($boostPaymentId > 0) {
            $boostPayment = Payment::query()->find($boostPaymentId);
            if (
                $boostPayment !== null
                && $boostPayment->purpose === PaymentPurpose::Boost
                && $boostPayment->isCompleted()
                && ! $boostPayment->is_consumed
            ) {
                $this->boostPurchaseService->markPaidAndQueueForAdmin($boostPayment);
            }
        }

        return $business->fresh(['subscription']);
    }

    /**
     * @return array{
     *     subscription_payment: Payment,
     *     boost_payment: Payment|null,
     *     total_amount: float,
     *     currency: string,
     * }
     */
    public function checkoutFromSubscriptionPayment(Payment $subscriptionPayment): array
    {
        $meta = is_array($subscriptionPayment->metadata) ? $subscriptionPayment->metadata : [];
        $boostPaymentId = isset($meta['boost_payment_id']) ? (int) $meta['boost_payment_id'] : 0;
        $boostPayment = $boostPaymentId > 0
            ? Payment::query()->find($boostPaymentId)
            : null;

        $totalAmount = (float) $subscriptionPayment->amount
            + (float) ($boostPayment?->amount ?? 0);

        return [
            'subscription_payment' => $subscriptionPayment,
            'boost_payment' => $boostPayment,
            'total_amount' => $totalAmount,
            'currency' => $subscriptionPayment->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriptionPayload(BusinessInfo $business): array
    {
        $business = $this->syncExpiredSubscription($business);
        $subscription = $this->subscriptionRecord($business);
        $expiresAt = $subscription->expires_at;

        return [
            'plan' => $subscription->plan->value,
            'plan_label' => $subscription->plan->label(),
            'status' => $subscription->status->value,
            'status_label' => $subscription->status->label(),
            'expires_at' => $expiresAt ? humanDateTime($expiresAt) : null,
            'expires_at_iso' => $expiresAt?->toIso8601String(),
            'is_expired' => $this->isSubscriptionExpired($subscription),
            'days_remaining' => $expiresAt && $expiresAt->isFuture()
                ? max(0, (int) now()->diffInDays($expiresAt, false))
                : 0,
            'requires_payment' => $this->requiresPayment($business),
            'can_pay_premium' => $this->canPayForPremium($business),
            'is_premium_active' => $this->hasActivePremium($business),
            'can_access_features' => $this->canAccessVendorFeatures($business),
            'photo_limit' => $this->maxCoverPhotos($business),
            'free_photo_limit' => $this->freePhotoLimit(),
            'premium_photo_limit' => $this->premiumPhotoLimit(),
            'is_verified' => $this->isBusinessVerified($business),
            'can_boost' => $this->canUseBoost($business),
            'analytics_locked' => ! $this->hasActivePremium($business),
            'is_trial' => $subscription->status === SubscriptionStatus::Trialing,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'trial_days_remaining' => $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture()
                ? max(0, (int) now()->diffInDays($subscription->trial_ends_at, false))
                : 0,
            'trial_eligible' => $this->isTrialEligible($business),
        ];
    }

    public function isTrialEligible(BusinessInfo $business): bool
    {
        if ($this->hasActivePremium($business) || ! $this->isBusinessVerified($business)) {
            return false;
        }

        return ! SubscriptionTrial::query()
            ->where('business_info_id', $business->id)
            ->exists();
    }

    public function startTrial(BusinessInfo $business, string $packageKey): BusinessInfo
    {
        if ($this->hasActivePremium($business)) {
            throw new RuntimeException('Premium subscription is already active.');
        }

        if (! $this->isBusinessVerified($business)) {
            throw new RuntimeException('Only verified vendors are eligible for a free trial.');
        }

        $package = $this->pricingPackageService->findActiveSubscriptionPackageModel($packageKey);

        if ($package === null || ! $package->trial_eligible || $package->trial_duration_days === null) {
            throw new RuntimeException('This plan does not offer a free trial.');
        }

        $alreadyTrialed = SubscriptionTrial::query()
            ->where('business_info_id', $business->id)
            ->exists();

        if ($alreadyTrialed) {
            throw new RuntimeException('This business has already used its free trial.');
        }

        $trialEndsAt = now()->addDays(max(1, (int) $package->trial_duration_days));

        return DB::transaction(function () use ($business, $package, $trialEndsAt): BusinessInfo {
            $subscription = $this->subscriptionRecord($business);
            $subscription->update([
                'plan' => SubscriptionPlan::Premium,
                'status' => SubscriptionStatus::Trialing,
                'pricing_package_id' => $package->id,
                'expires_at' => null,
                'trial_ends_at' => $trialEndsAt,
            ]);

            $business->update(['business_status' => BusinessStatus::Active]);

            SubscriptionTrial::query()->create([
                'business_info_id' => $business->id,
                'pricing_package_id' => $package->id,
                'started_at' => now(),
                'ends_at' => $trialEndsAt,
            ]);

            return $business->fresh(['subscription']);
        });
    }

    public function cancelSubscription(BusinessInfo $business): BusinessInfo
    {
        $subscription = $this->subscriptionRecord($business);

        if ($subscription->plan !== SubscriptionPlan::Premium) {
            throw new RuntimeException('This business does not have an active premium subscription.');
        }

        return $this->downgradeToFree($business, TrialEndedReason::Cancelled);
    }

    /**
     * Revert a business to the Free plan, closing out any open trial audit
     * row. Shared by explicit cancellation and scheduled trial expiry.
     */
    public function downgradeToFree(BusinessInfo $business, TrialEndedReason $reason): BusinessInfo
    {
        $subscription = $this->subscriptionRecord($business);

        return DB::transaction(function () use ($subscription, $business, $reason): BusinessInfo {
            $subscription->update([
                'plan' => SubscriptionPlan::Free,
                'status' => SubscriptionStatus::Active,
                'expires_at' => null,
                'trial_ends_at' => null,
            ]);

            $business->update(['business_status' => BusinessStatus::Inactive]);

            SubscriptionTrial::query()
                ->where('business_info_id', $business->id)
                ->whereNull('ended_reason')
                ->update(['ended_reason' => $reason]);

            return $business->fresh(['subscription']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function onboardingPayload(?BusinessInfo $business): array
    {
        if ($business === null) {
            return [
                'has_business' => false,
                'can_access_onboarding' => true,
                'redirect_to' => '/user/profile',
                'business_id' => null,
                'subscription' => null,
            ];
        }

        $business = $this->syncExpiredSubscription($business);
        $subscription = $this->subscriptionPayload($business);

        $redirectTo = null;
        if ($subscription['requires_payment']) {
            $redirectTo = '/vendor/premium-payment';
        }

        return [
            'has_business' => true,
            'can_access_onboarding' => false,
            'redirect_to' => $redirectTo,
            'business_id' => $business->id,
            'subscription' => $subscription,
        ];
    }

    public function createForBusiness(
        BusinessInfo $business,
        SubscriptionPlan $plan,
        SubscriptionStatus $status,
        ?\DateTimeInterface $expiresAt = null,
    ): BusinessSubscription {
        return $business->subscription()->create([
            'plan' => $plan,
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }

    private function subscriptionRecord(BusinessInfo $business): BusinessSubscription
    {
        $subscription = $business->subscription()->latest('id')->first();

        if ($subscription instanceof BusinessSubscription) {
            return $subscription;
        }

        return $business->subscription()->create([
            'plan' => SubscriptionPlan::Free,
            'status' => SubscriptionStatus::Active,
        ]);
    }
}
