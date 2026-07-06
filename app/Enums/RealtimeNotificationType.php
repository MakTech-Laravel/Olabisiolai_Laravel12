<?php

declare(strict_types=1);

namespace App\Enums;

enum RealtimeNotificationType: string
{
    case NewMessage = 'new_message';
    case NewFollow = 'new_follow';
    case VerificationApproved = 'verification_approved';
    case VerificationFlagged = 'verification_flagged';
    case VerificationRevoked = 'verification_revoked';
    case VerificationReverificationGranted = 'verification_reverification_granted';
    case VerificationSubmitted = 'verification_submitted';
    case PaymentCompleted = 'payment_completed';
    case ReferralRewardPaid = 'referral_reward_paid';
    case SystemAnnouncement = 'system_announcement';

    public function defaultTitle(): string
    {
        return match ($this) {
            self::NewMessage => 'New message',
            self::NewFollow => 'New follower',
            self::VerificationApproved => 'Verification approved',
            self::VerificationFlagged => 'Verification update',
            self::VerificationRevoked => 'Verification revoked',
            self::VerificationReverificationGranted => 'Re-verification granted',
            self::VerificationSubmitted => 'New verification request',
            self::PaymentCompleted => 'Payment received',
            self::ReferralRewardPaid => 'Referral reward',
            self::SystemAnnouncement => 'Announcement',
        };
    }

    public function defaultTone(): string
    {
        return match ($this) {
            self::NewMessage, self::NewFollow => 'info',
            self::VerificationApproved, self::PaymentCompleted, self::ReferralRewardPaid => 'success',
            self::VerificationFlagged, self::VerificationRevoked => 'warning',
            self::VerificationReverificationGranted, self::VerificationSubmitted => 'info',
            self::SystemAnnouncement => 'info',
        };
    }
}
