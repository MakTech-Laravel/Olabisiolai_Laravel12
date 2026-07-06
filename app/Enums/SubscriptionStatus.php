<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case PendingPayment = 'pending_payment';
    case Expired = 'expired';
    case Trialing = 'trialing';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::PendingPayment => 'Pending payment',
            self::Expired => 'Expired',
            self::Trialing => 'Trialing',
            self::Cancelled => 'Cancelled',
        };
    }
}
