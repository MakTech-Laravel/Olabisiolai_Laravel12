<?php

namespace App\Enums;

enum PaymentPurpose: string
{
    case Verification = 'verification';
    case Boost = 'boosting';
    case Subscription = 'subscription';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    public function label(): string
    {
        return match ($this) {
            self::Verification => 'Verification',
            self::Boost => 'Boosting',
            self::Subscription => 'Subscription',
        };
    }
}
