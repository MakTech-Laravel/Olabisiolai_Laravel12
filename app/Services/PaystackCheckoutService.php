<?php

namespace App\Services;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use RuntimeException;

class PaystackCheckoutService
{
    public function __construct(
        private readonly PaystackService $paystackService,
        private readonly PaymentService $paymentService,
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

        $payment = $this->preparePaystackPaymentReference($payment);

        $initialized = $this->initializePaystackCheckout($email, $gatewayAmount, $payment);
        $payment = $payment->fresh();

        $accessCode = (string) ($initialized['access_code'] ?? '');
        if ($accessCode === '') {
            throw new RuntimeException('Paystack could not start checkout. Please try again.');
        }

        $responseData['paystack_access_code'] = $accessCode;
        $responseData['payment'] = $this->paymentService->toArray($payment);

        if (isset($responseData['payments']) && is_array($responseData['payments'])) {
            $payments = $responseData['payments'];
            if (isset($payments['subscription'])) {
                $payments['subscription'] = $this->paymentService->toArray($payment);
            }
            $responseData['payments'] = $payments;
        }

        return $responseData;
    }

    private function preparePaystackPaymentReference(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return $payment;
        }

        return $this->paymentService->refreshTransactionReference($payment);
    }

    /**
     * @return array<string, mixed>
     */
    private function initializePaystackCheckout(string $email, float $gatewayAmount, Payment $payment): array
    {
        try {
            return $this->paystackService->initializeTransaction(
                $email,
                (int) round($gatewayAmount * 100),
                (string) $payment->tx_ref,
                (string) $payment->currency,
            );
        } catch (RuntimeException $exception) {
            if (! $this->isDuplicatePaystackReferenceError($exception)) {
                throw $exception;
            }

            $payment = $this->paymentService->refreshTransactionReference($payment);

            return $this->paystackService->initializeTransaction(
                $email,
                (int) round($gatewayAmount * 100),
                (string) $payment->tx_ref,
                (string) $payment->currency,
            );
        }
    }

    private function isDuplicatePaystackReferenceError(RuntimeException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate') && str_contains($message, 'reference');
    }
}
