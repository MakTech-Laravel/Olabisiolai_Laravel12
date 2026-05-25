<?php

declare(strict_types=1);

namespace App\Enums;

enum RealtimeNotificationType: string
{
    case NewMessage = 'new_message';
    case VerificationApproved = 'verification_approved';
    case VerificationFlagged = 'verification_flagged';
    case VerificationRevoked = 'verification_revoked';
    case VerificationSubmitted = 'verification_submitted';
    case PaymentCompleted = 'payment_completed';
    case SystemAnnouncement = 'system_announcement';

    public function defaultTitle(): string
    {
        return match ($this) {
            self::NewMessage => 'New message',
            self::VerificationApproved => 'Verification approved',
            self::VerificationFlagged => 'Verification update',
            self::VerificationRevoked => 'Verification revoked',
            self::VerificationSubmitted => 'New verification request',
            self::PaymentCompleted => 'Payment received',
            self::SystemAnnouncement => 'Announcement',
        };
    }

    public function defaultTone(): string
    {
        return match ($this) {
            self::NewMessage => 'info',
            self::VerificationApproved, self::PaymentCompleted => 'success',
            self::VerificationFlagged, self::VerificationRevoked => 'warning',
            self::VerificationSubmitted => 'info',
            self::SystemAnnouncement => 'info',
        };
    }
}
