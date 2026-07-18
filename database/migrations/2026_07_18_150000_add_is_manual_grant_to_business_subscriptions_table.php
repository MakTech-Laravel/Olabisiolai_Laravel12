<?php

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->boolean('is_manual_grant')->default(false)->after('trial_ends_at');
            $table->index(['is_manual_grant', 'status']);
        });

        $manualBusinessIds = DB::table('payments')
            ->where('purpose', PaymentPurpose::Subscription->value)
            ->where('status', PaymentStatus::Completed->value)
            ->whereNotNull('business_info_id')
            ->where(function ($query): void {
                $query->where('metadata->manual_grant', true)
                    ->orWhere('metadata->manual_grant', 'true')
                    ->orWhere('metadata->manual_grant', 1);
            })
            ->pluck('business_info_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($manualBusinessIds !== []) {
            DB::table('business_subscriptions')
                ->whereIn('business_info_id', $manualBusinessIds)
                ->where('plan', SubscriptionPlan::Premium->value)
                ->whereIn('status', [
                    SubscriptionStatus::Active->value,
                    SubscriptionStatus::Trialing->value,
                ])
                ->update(['is_manual_grant' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['is_manual_grant', 'status']);
            $table->dropColumn('is_manual_grant');
        });
    }
};
