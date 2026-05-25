<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Messaging presence (online / offline / away).
 * Account lifecycle status remains {@see UserStatus}.
 */
enum PresenceUserStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Away = 'away';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
