<?php

namespace App\Enums;

enum AdminStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Block = 'block';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
