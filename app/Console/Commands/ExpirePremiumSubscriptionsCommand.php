<?php

namespace App\Console\Commands;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Enums\TrialEndedReason;
use App\Models\BusinessInfo;
use App\Models\BusinessSubscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePremiumSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Flip premium subscriptions past their expiry to expired and deactivate the business, in bulk';

    public function __construct(private readonly SubscriptionService $subscriptionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->expirePremiumSubscriptions();
        $this->expireTrials();

        return self::SUCCESS;
    }

    private function expirePremiumSubscriptions(): void
    {
        $expired = BusinessSubscription::query()
            ->where('plan', SubscriptionPlan::Premium->value)
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get(['id', 'business_info_id']);

        if ($expired->isEmpty()) {
            $this->info('No expired premium subscriptions found.');

            return;
        }

        DB::transaction(function () use ($expired): void {
            BusinessSubscription::query()
                ->whereIn('id', $expired->pluck('id'))
                ->update(['status' => SubscriptionStatus::Expired->value]);

            BusinessInfo::query()
                ->whereIn('id', $expired->pluck('business_info_id'))
                ->update(['business_status' => BusinessStatus::Inactive->value]);
        });

        $this->info("Expired {$expired->count()} premium subscription(s).");
    }

    private function expireTrials(): void
    {
        $expiredTrialBusinessIds = BusinessSubscription::query()
            ->where('status', SubscriptionStatus::Trialing->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->pluck('business_info_id');

        if ($expiredTrialBusinessIds->isEmpty()) {
            $this->info('No expired trials found.');

            return;
        }

        BusinessInfo::query()
            ->whereIn('id', $expiredTrialBusinessIds)
            ->each(fn (BusinessInfo $business) => $this->subscriptionService->downgradeToFree($business, TrialEndedReason::Expired));

        $this->info("Expired {$expiredTrialBusinessIds->count()} trial(s).");
    }
}
