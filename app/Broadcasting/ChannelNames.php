<?php

declare(strict_types=1);

namespace App\Broadcasting;

/**
 * Central channel name helpers — keep Laravel events and Echo subscriptions in sync.
 */
final class ChannelNames
{
    public const PUBLIC_ANNOUNCEMENTS = 'announcements';

    public const ADMIN_NOTIFICATIONS = 'admins';

    public static function user(int $userId): string
    {
        return 'user.'.$userId;
    }

    public static function admin(int $adminId): string
    {
        return 'admin.'.$adminId;
    }

    public static function conversation(int $conversationId): string
    {
        return 'conversation.'.$conversationId;
    }
}
