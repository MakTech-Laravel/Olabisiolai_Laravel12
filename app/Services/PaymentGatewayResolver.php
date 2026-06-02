<?php

namespace App\Services;

use App\Enums\PaymentGateway;
use App\Models\Payment;

class PaymentGatewayResolver
{
    public function resolveForDisplay(Payment $payment): ?PaymentGateway
    {
        if ($payment->gateway instanceof PaymentGateway) {
            return $payment->gateway;
        }

        // Legacy rows (created before gateway column existed) were Flutterwave card checkouts.
        if ($payment->gateway_transaction_id !== null && $payment->gateway_transaction_id !== '') {
            return PaymentGateway::Flutterwave;
        }

        return null;
    }
}
