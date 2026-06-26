<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Broadcasting\ChannelNames;
use App\Enums\RealtimeNotificationType;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Single reusable realtime notification (database + Reverb).
 * Add new types via {@see RealtimeNotificationType} and a static factory below.
 *
 * @phpstan-type Payload array<string, mixed>
 */
final class RealtimeNotification extends Notification implements ShouldBroadcastNow, ShouldQueue
{
    use Queueable;

    private const RECIPIENT_USER = 'user';

    private const RECIPIENT_ADMIN = 'admin';

    private function __construct(
        private readonly string $recipientKind,
        private readonly int $recipientId,
        public readonly RealtimeNotificationType $notificationType,
        public readonly string $title,
        public readonly string $message,
        /** @var Payload */
        public readonly array $data = [],
        public readonly ?string $actionUrl = null,
        public readonly ?string $tone = null,
        private readonly ?string $broadcastAsOverride = null,
        /** @var Payload */
        private readonly array $topLevelPayload = [],
    ) {}

    // -------------------------------------------------------------------------
    // Static factories (one place for all app notification shapes)
    // -------------------------------------------------------------------------

    public static function newMessage(
        int $recipientUserId,
        int $senderId,
        string $conversationUuid,
        string $senderName,
        string $preview,
        int $unreadCount,
        ?string $actionUrl = null,
        bool $fromPlatformAdmin = false,
        ?int $businessInfoId = null,
    ): self {
        return self::forUser(
            userId: $recipientUserId,
            type: RealtimeNotificationType::NewMessage,
            title: $senderName,
            message: $preview,
            data: [
                'sender_id' => $senderId,
                'conversation_uuid' => $conversationUuid,
                'sender_name' => $senderName,
                'preview' => $preview,
                'unread_count' => $unreadCount,
                'from_platform_admin' => $fromPlatformAdmin,
                'business_info_id' => $businessInfoId,
            ],
            actionUrl: $actionUrl,
            broadcastAs: 'new_message',
            topLevelPayload: [
                'sender_id' => $senderId,
                'conversation_uuid' => $conversationUuid,
                'sender_name' => $senderName,
                'preview' => $preview,
                'unread_count' => $unreadCount,
                'from_platform_admin' => $fromPlatformAdmin,
                'business_info_id' => $businessInfoId,
            ],
        );
    }

    public static function newFollow(
        int $recipientUserId,
        int $recipientBusinessId,
        int $followerId,
        string $followerName,
        string $followerUuid,
        string $followerRole,
        ?int $followerBusinessId = null,
    ): self {
        $actionUrl = "/user/business-followers?business_id={$recipientBusinessId}";

        return self::forUser(
            userId: $recipientUserId,
            type: RealtimeNotificationType::NewFollow,
            title: 'New follower',
            message: "{$followerName} followed your business",
            data: [
                'follower_id' => $followerId,
                'follower_name' => $followerName,
                'follower_uuid' => $followerUuid,
                'follower_role' => $followerRole,
                'follower_business_id' => $followerBusinessId,
            ],
            actionUrl: $actionUrl,
            tone: 'info',
        );
    }

    public static function verificationApproved(
        int $recipientUserId,
        string $businessName,
        ?string $note = null,
    ): self {
        return self::forUser(
            userId: $recipientUserId,
            type: RealtimeNotificationType::VerificationApproved,
            title: 'Verification approved',
            message: sprintf('"%s" has been verified successfully.', $businessName),
            data: ['business_name' => $businessName, 'note' => $note],
            actionUrl: '/vendor/verification',
            tone: 'success',
        );
    }

    public static function verificationFlagged(
        int $recipientUserId,
        string $businessName,
        string $reason,
    ): self {
        return self::forUser(
            userId: $recipientUserId,
            type: RealtimeNotificationType::VerificationFlagged,
            title: 'Verification needs attention',
            message: sprintf('"%s" requires changes: %s', $businessName, $reason),
            data: ['business_name' => $businessName, 'reason' => $reason],
            actionUrl: '/vendor/verification',
            tone: 'warning',
        );
    }

    public static function verificationRevoked(
        int $recipientUserId,
        string $businessName,
        ?string $reason = null,
    ): self {
        $message = $reason !== null && $reason !== ''
            ? sprintf('Verification for "%s" was removed by admin: %s', $businessName, $reason)
            : sprintf('Verification for "%s" was removed. Your business is no longer verified.', $businessName);

        return self::forUser(
            userId: $recipientUserId,
            type: RealtimeNotificationType::VerificationRevoked,
            title: 'Verification revoked',
            message: $message,
            data: ['business_name' => $businessName, 'reason' => $reason],
            actionUrl: '/vendor/verification',
            tone: 'warning',
        );
    }

    public static function verificationSubmitted(
        int $recipientAdminId,
        int $businessInfoId,
        string $businessName,
        string $vendorName,
    ): self {
        return self::forAdmin(
            adminId: $recipientAdminId,
            type: RealtimeNotificationType::VerificationSubmitted,
            title: 'New verification request',
            message: sprintf('%s submitted verification for "%s".', $vendorName, $businessName),
            data: [
                'business_info_id' => $businessInfoId,
                'business_name' => $businessName,
                'vendor_name' => $vendorName,
            ],
            actionUrl: '/admin/verification',
            tone: 'info',
        );
    }

    public static function paymentCompleted(
        int $recipientUserId,
        string $purposeLabel,
        float $amount,
        string $currency,
    ): self {
        return self::forUser(
            userId: $recipientUserId,
            type: RealtimeNotificationType::PaymentCompleted,
            title: 'Payment successful',
            message: sprintf(
                'Your %s payment of %s %s was completed.',
                $purposeLabel,
                $currency,
                number_format($amount, 2),
            ),
            data: [
                'purpose' => $purposeLabel,
                'amount' => $amount,
                'currency' => $currency,
            ],
            actionUrl: '/vendor/verification',
            tone: 'success',
        );
    }

    /**
     * @param  Payload  $data
     * @param  Payload  $topLevelPayload
     */
    public static function forUser(
        int $userId,
        RealtimeNotificationType $type,
        string $title,
        string $message,
        array $data = [],
        ?string $actionUrl = null,
        ?string $tone = null,
        ?string $broadcastAs = null,
        array $topLevelPayload = [],
    ): self {
        return new self(
            recipientKind: self::RECIPIENT_USER,
            recipientId: $userId,
            notificationType: $type,
            title: $title,
            message: $message,
            data: $data,
            actionUrl: $actionUrl,
            tone: $tone,
            broadcastAsOverride: $broadcastAs,
            topLevelPayload: $topLevelPayload,
        );
    }

    /**
     * @param  Payload  $data
     */
    public static function forAdmin(
        int $adminId,
        RealtimeNotificationType $type,
        string $title,
        string $message,
        array $data = [],
        ?string $actionUrl = null,
        ?string $tone = null,
        ?string $broadcastAs = null,
    ): self {
        return new self(
            recipientKind: self::RECIPIENT_ADMIN,
            recipientId: $adminId,
            notificationType: $type,
            title: $title,
            message: $message,
            data: $data,
            actionUrl: $actionUrl,
            tone: $tone,
            broadcastAsOverride: $broadcastAs,
        );
    }

    // -------------------------------------------------------------------------

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return Payload
     */
    public function toArray(object $notifiable): array
    {
        $base = [
            'type' => $this->notificationType->value,
            'title' => $this->title,
            'message' => $this->message,
            'tone' => $this->tone ?? $this->notificationType->defaultTone(),
            'action_url' => $this->actionUrl,
            'data' => $this->data,
        ];

        return $this->topLevelPayload === []
            ? $base
            : array_merge($base, $this->topLevelPayload);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return $this->broadcastAsOverride ?? 'app.notification';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channel = $this->recipientKind === self::RECIPIENT_ADMIN
            ? ChannelNames::admin($this->recipientId)
            : ChannelNames::user($this->recipientId);

        return [new PrivateChannel($channel)];
    }
}
