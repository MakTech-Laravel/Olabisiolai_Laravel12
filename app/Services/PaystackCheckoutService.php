<?php

namespace App\Services;

use App\Enums\PaymentGateway;
use App\Models\Payment;
use App\Models\User;
use RuntimeException;

class PaystackCheckoutService
{
    public function __construct(
        private readonly PaystackService $paystackService,
    ) {}

    /**
     * @param  array<string, mixed>  $responseData
     * @return array<string, mixed>
     */
    public function appendAccessCodeIfNeeded(
        array $responseData,
        User $user,
        Payment $payment,
        ?PaymentGateway $gateway,
        float $gatewayAmount,
    ): array {
        if ($gateway !== PaymentGateway::Paystack || $gatewayAmount <= 0) {
            return $responseData;
        }

        $email = trim((string) $user->email);
        if ($email === '') {
            throw new RuntimeException('Your account email is required for Paystack checkout.');
        }

        $initialized = $this->paystackService->initializeTransaction(
            $email,
            (int) round($gatewayAmount * 100),
            (string) $payment->tx_ref,
            (string) $payment->currency,
        );

        $accessCode = (string) ($initialized['access_code'] ?? '');
        if ($accessCode === '') {
            throw new RuntimeException('Paystack could not start checkout. Please try again.');
        }

        $responseData['paystack_access_code'] = $accessCode;

        return $responseData;
    }
}
