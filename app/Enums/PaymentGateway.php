<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Flutterwave = 'flutterwave';
    case Paystack = 'paystack';
    case Wallet = 'wallet';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
