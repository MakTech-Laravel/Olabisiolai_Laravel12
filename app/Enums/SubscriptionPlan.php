<?php

namespace App\Enums;

enum SubscriptionPlan: string
{
    case Free = 'free';
    case Premium = 'premium';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Premium => 'Premium',
        };
    }
}
