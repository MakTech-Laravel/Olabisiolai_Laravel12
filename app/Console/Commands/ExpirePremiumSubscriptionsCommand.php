<?php

namespace App\Console\Commands;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\BusinessInfo;
use App\Models\BusinessSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePremiumSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Flip premium subscriptions past their expiry to expired and deactivate the business, in bulk';

    public function handle(): int
    {
        $expired = BusinessSubscription::query()
            ->where('plan', SubscriptionPlan::Premium->value)
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get(['id', 'business_info_id']);

        if ($expired->isEmpty()) {
            $this->info('No expired premium subscriptions found.');

            return self::SUCCESS;
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

        return self::SUCCESS;
    }
}
