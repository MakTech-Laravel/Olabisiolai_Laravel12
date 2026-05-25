<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BusinessStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\LgaBoost;
use App\Models\Location;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class VendorSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );
    }

    public function test_premium_business_creation_requires_payment_before_vendor_features(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $createResponse = $this->withToken($token)->post('/api/v1/vendor/business/create', [
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => 'Premium Shop',
            'business_description' => 'Premium vendor business.',
            'services' => ['Consulting'],
            'phone' => '+2348012345678',
            'subscription_plan' => 'premium',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'cover_photos' => [UploadedFile::fake()->image('cover.png')],
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('data.requires_subscription_payment', true);

        $business = BusinessInfo::query()->where('user_id', $user->id)->with('subscription')->first();
        $this->assertNotNull($business);
        $this->assertNotNull($business->subscription);
        $this->assertSame(SubscriptionPlan::Premium, $business->subscription->plan);
        $this->assertSame(SubscriptionStatus::PendingPayment, $business->subscription->status);
        $this->assertSame(VerificationStatus::None, $business->verification_status);
        $this->assertSame(BusinessStatus::Inactive, $business->business_status);

        $blockedResponse = $this->withToken($token)->getJson('/api/v1/vendor/business/show');
        $blockedResponse->assertStatus(402);

        $allowedResponse = $this->withToken($token)->getJson('/api/v1/vendor/subscription/status');
        $allowedResponse->assertOk();
        $allowedResponse->assertJsonPath('data.subscription.requires_payment', true);
    }

    public function test_premium_payment_confirmation_activates_subscription_without_verification(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $business = BusinessInfo::factory()
            ->for($user)
            ->premiumPending()
            ->create([
                'verification_status' => VerificationStatus::None,
                'business_status' => BusinessStatus::Inactive,
            ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Subscription,
            'package_id' => 'premium_yearly',
            'status' => PaymentStatus::Pending,
        ]);

        $token = $user->createToken('test')->accessToken;

        $confirmResponse = $this->withToken($token)->postJson('/api/v1/vendor/subscription/payment/confirm', [
            'payment_id' => $payment->id,
            'gateway_transaction_id' => 'FLW-SUB-12345',
        ]);

        $confirmResponse->assertOk();
        $confirmResponse->assertJsonPath('data.subscription.plan', 'premium');
        $confirmResponse->assertJsonPath('data.subscription.status', 'active');
        $business->refresh();
        $business->load('subscription');
        $this->assertSame(SubscriptionStatus::Active, $business->subscription->status);
        $this->assertSame(VerificationStatus::None, $business->verification_status);
        $this->assertSame(BusinessStatus::Active, $business->business_status);
        $this->assertNull($business->verified_at);
        $this->assertNotNull($business->subscription->expires_at);
        $this->assertTrue($business->subscription->expires_at->greaterThan(now()->addDays(364)));

        $showResponse = $this->withToken($token)->getJson('/api/v1/vendor/business/show');
        $showResponse->assertOk();
        $showResponse->assertJsonPath('data.business.shows_verified_badge', false);
        $showResponse->assertJsonPath('data.business.is_verified', false);
        $showResponse->assertJsonPath('data.business.is_premium_active', true);
    }

    public function test_free_business_can_start_premium_checkout(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        BusinessInfo::factory()->for($user)->create();

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/vendor/subscription/payment/init');

        $response->assertCreated();
        $business = BusinessInfo::query()->where('user_id', $user->id)->with('subscription')->first();
        $this->assertNotNull($business?->subscription);
        $this->assertSame(SubscriptionPlan::Premium, $business->subscription->plan);
        $this->assertSame(SubscriptionStatus::PendingPayment, $business->subscription->status);
    }

    public function test_premium_checkout_with_boost_creates_separate_payment_records(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $location = Location::factory()->create();
        LgaBoost::query()->create([
            'location_id' => $location->id,
            'enabled' => true,
            'tiers' => [
                [
                    'key' => 'top_10',
                    'label' => 'Top 10 Boost',
                    'total_slots' => 10,
                    'durations' => [
                        ['days' => 7, 'enabled' => true, 'price_amount' => 3000],
                    ],
                ],
            ],
            'durations' => [],
            'total_slots' => 10,
            'slots_sold' => 0,
            'slots_remaining' => 10,
            'active_boosts' => 0,
            'expired_boosts' => 0,
        ]);
        BusinessInfo::factory()->for($user)->create([
            'location_id' => $location->id,
        ]);

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/vendor/subscription/payment/init', [
            'boost_tier_key' => 'top_10',
            'boost_duration_days' => 7,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.payments.subscription.purpose', 'subscription');
        $response->assertJsonPath('data.payments.boost.purpose', 'boosting');

        $subAmount = (float) $response->json('data.payments.subscription.amount');
        $boostAmount = (float) $response->json('data.payments.boost.amount');
        $this->assertSame($subAmount + $boostAmount, (float) $response->json('data.total_amount'));

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'purpose' => PaymentPurpose::Subscription->value,
            'amount' => $subAmount,
        ]);
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'purpose' => PaymentPurpose::Boost->value,
            'amount' => $boostAmount,
        ]);
    }

    public function test_free_business_payment_confirmation_activates_premium(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $business = BusinessInfo::factory()->for($user)->create();

        $token = $user->createToken('test')->accessToken;

        $initResponse = $this->withToken($token)->postJson('/api/v1/vendor/subscription/payment/init');
        $initResponse->assertCreated();

        $paymentId = (int) $initResponse->json('data.payments.subscription.id');

        $confirmResponse = $this->withToken($token)->postJson('/api/v1/vendor/subscription/payment/confirm', [
            'payment_id' => $paymentId,
            'gateway_transaction_id' => 'FLW-FREE-UPGRADE-001',
        ]);

        $confirmResponse->assertOk();
        $confirmResponse->assertJsonPath('data.subscription.plan', 'premium');
        $confirmResponse->assertJsonPath('data.subscription.status', 'active');
        $confirmResponse->assertJsonPath('data.subscription.is_premium_active', true);
        $confirmResponse->assertJsonPath('data.subscription.requires_payment', false);

        $business->refresh();
        $business->load('subscription');
        $this->assertSame(SubscriptionStatus::Active, $business->subscription->status);
        $this->assertSame(BusinessStatus::Active, $business->business_status);
    }

    public function test_free_business_has_no_verified_badge_and_full_vendor_access(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $business = BusinessInfo::factory()->for($user)->create([
            'verification_status' => VerificationStatus::None,
        ]);

        $token = $user->createToken('test')->accessToken;

        $showResponse = $this->withToken($token)->getJson('/api/v1/vendor/business/show');
        $showResponse->assertOk();
        $showResponse->assertJsonPath('data.business.shows_verified_badge', false);
        $showResponse->assertJsonPath('data.business.subscription_plan', 'free');
        $showResponse->assertJsonPath('data.business.can_access_features', true);

        $this->assertSame($business->id, $showResponse->json('data.business.id'));
    }
}
