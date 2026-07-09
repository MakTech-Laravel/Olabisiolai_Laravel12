<?php

namespace Tests\Unit;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaystackCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_payment_gets_fresh_reference_before_paystack_initialize(): void
    {
        config([
            'services.paystack.secret' => 'sk_test_example',
            'services.paystack.base_url' => 'https://api.paystack.co',
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['access_code' => 'access_code_123'],
            ], 200),
        ]);

        $user = User::factory()->create(['email' => 'vendor@example.com']);
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'purpose' => PaymentPurpose::Subscription,
            'status' => PaymentStatus::Pending,
            'tx_ref' => 'subscription_1_oldreference',
            'amount' => 5000,
            'currency' => 'NGN',
        ]);

        $originalRef = $payment->tx_ref;

        $result = app(PaystackCheckoutService::class)->appendAccessCodeIfNeeded(
            ['payments' => ['subscription' => []]],
            $user,
            $payment,
            PaymentGateway::Paystack,
            5000,
        );

        $payment->refresh();

        $this->assertSame('access_code_123', $result['paystack_access_code']);
        $this->assertNotSame($originalRef, $payment->tx_ref);
        $this->assertSame($payment->tx_ref, $result['payments']['subscription']['tx_ref']);

        Http::assertSent(function ($request) use ($payment): bool {
            $body = $request->data();

            return ($body['reference'] ?? null) === $payment->tx_ref;
        });
    }
}
