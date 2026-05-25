<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\BusinessInfo;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorPaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class VendorPaymentsApiTest extends TestCase
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

    public function test_vendor_can_list_and_view_own_payments(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $business = BusinessInfo::factory()->create(['user_id' => $user->id]);

        $mine = Payment::factory()->completed()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Subscription,
            'metadata' => ['package_title' => 'Premium'],
        ]);

        $otherUser = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        Payment::factory()->completed()->create([
            'user_id' => $otherUser->id,
            'business_info_id' => BusinessInfo::factory()->create(['user_id' => $otherUser->id])->id,
            'purpose' => PaymentPurpose::Verification,
        ]);

        $list = $this->withToken($token)->getJson('/api/v1/vendor/payments');
        $list->assertOk();
        $list->assertJsonPath('success', true);
        $list->assertJsonPath('data.pagination.total', 1);
        $list->assertJsonPath('data.items.0.id', $mine->id);
        $list->assertJsonPath('data.subscription_month_range.has_subscription_history', true);
        $list->assertJsonPath('data.subscription_month_range.end_month', now()->format('Y-m'));

        $show = $this->withToken($token)->getJson('/api/v1/vendor/payments/' . $mine->id);
        $show->assertOk();
        $show->assertJsonPath('data.payment.id', $mine->id);
        $show->assertJsonPath('data.payment.status', PaymentStatus::Completed->value);

        $missing = $this->withToken($token)->getJson('/api/v1/vendor/payments/999999');
        $missing->assertNotFound();
    }

    public function test_vendor_can_filter_payments_by_month_and_purpose(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;
        $business = BusinessInfo::factory()->create(['user_id' => $user->id]);

        $thisMonthKey = now()->format('Y-m');
        $oldMonth = now()->subMonths(4)->startOfMonth();
        $oldMonthKey = $oldMonth->format('Y-m');

        /** Earliest successful subscription defines the month filter window. */
        Payment::factory()->completed()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Subscription,
            'paid_at' => $oldMonth->copy()->addDays(2),
        ]);

        $currentMonthPayment = Payment::factory()->completed()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Subscription,
            'paid_at' => now(),
        ]);

        $oldMonthPayment = Payment::factory()->completed()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Verification,
            'paid_at' => $oldMonth->copy()->addDays(3),
        ]);

        $list = $this->withToken($token)->getJson('/api/v1/vendor/payments');
        $list->assertOk();
        $list->assertJsonPath('data.subscription_month_range.start_month', $oldMonthKey);
        $list->assertJsonPath('data.subscription_month_range.end_month', $thisMonthKey);
        $list->assertJsonPath('data.subscription_month_range.has_subscription_history', true);

        $byMonth = $this->withToken($token)->getJson('/api/v1/vendor/payments?month=' . $thisMonthKey);
        $byMonth->assertOk();
        $byMonth->assertJsonPath('data.pagination.total', 1);
        $byMonth->assertJsonPath('data.items.0.id', $currentMonthPayment->id);

        $byOldMonth = $this->withToken($token)->getJson(
            '/api/v1/vendor/payments?month=' . $oldMonthKey . '&purpose=verification',
        );
        $byOldMonth->assertOk();
        $byOldMonth->assertJsonPath('data.pagination.total', 1);
        $byOldMonth->assertJsonPath('data.items.0.id', $oldMonthPayment->id);

        $byPurpose = $this->withToken($token)->getJson('/api/v1/vendor/payments?purpose=verification');
        $byPurpose->assertOk();
        $byPurpose->assertJsonPath('data.pagination.total', 1);
        $byPurpose->assertJsonPath('data.items.0.id', $oldMonthPayment->id);
    }

    public function test_vendor_cannot_filter_by_month_outside_subscription_range(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;
        $business = BusinessInfo::factory()->create(['user_id' => $user->id]);

        Payment::factory()->completed()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Subscription,
            'paid_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/vendor/payments?month=' . now()->subYears(10)->format('Y-m'))
            ->assertUnprocessable();
    }

    public function test_vendor_can_download_payment_history_csv(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;
        $business = BusinessInfo::factory()->create(['user_id' => $user->id]);

        Payment::factory()->completed()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Subscription,
            'paid_at' => now(),
        ]);

        $response = $this->withToken($token)->get('/api/v1/vendor/payments/export');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('ID', $response->streamedContent());
        $this->assertStringContainsString('subscription', $response->streamedContent());
    }

    public function test_setting_default_clears_previous_default(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $first = $this->withToken($token)->postJson('/api/v1/vendor/payment-methods', [
            'cardholder_name' => 'First Profile',
            'email' => 'first@example.com',
            'phone' => '08011111111',
            'is_default' => true,
        ]);
        $first->assertCreated();
        $firstId = (int) $first->json('data.payment_method.id');

        $second = $this->withToken($token)->postJson('/api/v1/vendor/payment-methods', [
            'cardholder_name' => 'Second Profile',
            'email' => 'second@example.com',
            'phone' => '08022222222',
            'is_default' => false,
        ]);
        $second->assertCreated();
        $secondId = (int) $second->json('data.payment_method.id');

        $this->assertDatabaseHas('vendor_payment_methods', ['id' => $firstId, 'is_default' => true]);
        $this->assertDatabaseHas('vendor_payment_methods', ['id' => $secondId, 'is_default' => false]);

        $this->withToken($token)->patchJson('/api/v1/vendor/payment-methods/' . $secondId . '/default')->assertOk();

        $this->assertDatabaseHas('vendor_payment_methods', ['id' => $firstId, 'is_default' => false]);
        $this->assertDatabaseHas('vendor_payment_methods', ['id' => $secondId, 'is_default' => true]);
        $this->assertSame(1, VendorPaymentMethod::query()->where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_vendor_can_manage_saved_payment_methods(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $create = $this->withToken($token)->postJson('/api/v1/vendor/payment-methods', [
            'label' => 'Main',
            'cardholder_name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '08012345678',
            'last_four' => '4242',
            'card_brand' => 'Visa',
            'is_default' => true,
        ]);
        $create->assertCreated();
        $id = (int) $create->json('data.payment_method.id');

        $this->assertDatabaseHas('vendor_payment_methods', [
            'id' => $id,
            'user_id' => $user->id,
            'is_default' => true,
        ]);

        $second = $this->withToken($token)->postJson('/api/v1/vendor/payment-methods', [
            'cardholder_name' => 'Alan Turing',
            'email' => 'alan@example.com',
            'phone' => '08087654321',
            'is_default' => true,
        ]);
        $second->assertCreated();
        $this->assertDatabaseHas('vendor_payment_methods', [
            'user_id' => $user->id,
            'cardholder_name' => 'Ada Lovelace',
            'is_default' => false,
        ]);
        $this->assertDatabaseHas('vendor_payment_methods', [
            'user_id' => $user->id,
            'cardholder_name' => 'Alan Turing',
            'is_default' => true,
        ]);

        $this->withToken($token)->patchJson('/api/v1/vendor/payment-methods/' . $id . '/default')->assertOk();
        $this->assertTrue(VendorPaymentMethod::query()->find($id)?->is_default);

        $this->withToken($token)->deleteJson('/api/v1/vendor/payment-methods/' . $id)->assertOk();
        $this->assertDatabaseMissing('vendor_payment_methods', ['id' => $id]);
    }
}
