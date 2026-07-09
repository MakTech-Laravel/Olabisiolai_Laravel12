<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TrialEndedReason;
use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\Payment;
use App\Models\PricingPackage;
use App\Models\SubscriptionTrial;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PricingPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class VendorSubscriptionTrialUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PricingPackageSeeder::class);

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );
    }

    public function test_trialing_vendor_can_start_premium_checkout(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        BusinessInfo::factory()->for($user)->premiumTrialing(5)->create([
            'verification_status' => VerificationStatus::Approved,
            'verified_at' => now(),
        ]);

        $token = $user->createToken('test')->accessToken;

        $statusResponse = $this->withToken($token)->getJson('/api/v1/vendor/subscription/status');
        $statusResponse->assertOk();
        $statusResponse->assertJsonPath('data.subscription.is_trial', true);
        $statusResponse->assertJsonPath('data.subscription.can_pay_premium', true);
        $statusResponse->assertJsonPath('data.subscription.can_upgrade_from_trial', true);

        $initResponse = $this->withToken($token)->postJson('/api/v1/vendor/subscription/payment/init', [
            'package_key' => 'premium_monthly',
        ]);

        $initResponse->assertCreated();
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'purpose' => PaymentPurpose::Subscription->value,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_trial_upgrade_credits_remaining_days_to_monthly_plan(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $business = BusinessInfo::factory()->for($user)->premiumTrialing(4)->create([
            'verification_status' => VerificationStatus::Approved,
            'verified_at' => now(),
        ]);

        $monthlyPackage = PricingPackage::query()
            ->where('package_key', 'premium_monthly')
            ->firstOrFail();

        SubscriptionTrial::query()->create([
            'business_info_id' => $business->id,
            'pricing_package_id' => $monthlyPackage->id,
            'started_at' => now()->subDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Subscription,
            'package_id' => 'premium_monthly',
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
            'gateway_transaction_id' => 'FLW-TRIAL-MONTHLY',
            'metadata' => [
                'pricing_package_id' => $monthlyPackage->id,
                'line_item' => 'subscription',
            ],
        ]);

        $activated = app(SubscriptionService::class)->activatePremiumAfterPayment($payment, $user);
        $activated->load('subscription');

        $this->assertSame(SubscriptionStatus::Active, $activated->subscription->status);
        $this->assertNull($activated->subscription->trial_ends_at);
        $this->assertTrue($activated->subscription->expires_at->equalTo(now()->addDays(34)));

        $this->assertDatabaseHas('subscription_trials', [
            'business_info_id' => $business->id,
            'ended_reason' => TrialEndedReason::UpgradedToPaid->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_trial_upgrade_credits_remaining_days_to_yearly_plan(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $business = BusinessInfo::factory()->for($user)->premiumTrialing(4)->create([
            'verification_status' => VerificationStatus::Approved,
            'verified_at' => now(),
        ]);

        $yearlyPackage = PricingPackage::query()
            ->where('package_key', 'premium_yearly')
            ->firstOrFail();

        SubscriptionTrial::query()->create([
            'business_info_id' => $business->id,
            'pricing_package_id' => $yearlyPackage->id,
            'started_at' => now()->subDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Subscription,
            'package_id' => 'premium_yearly',
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
            'gateway_transaction_id' => 'FLW-TRIAL-YEARLY',
            'metadata' => [
                'pricing_package_id' => $yearlyPackage->id,
                'line_item' => 'subscription',
            ],
        ]);

        $activated = app(SubscriptionService::class)->activatePremiumAfterPayment($payment, $user);
        $activated->load('subscription');

        $this->assertTrue($activated->subscription->expires_at->equalTo(now()->addDays(369)));

        Carbon::setTestNow();
    }
}
