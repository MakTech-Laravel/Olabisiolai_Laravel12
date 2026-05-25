<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'Not Applied',
            self::Pending => 'Pending Review',
            self::Approved => 'Approved',
        };
    }
}
