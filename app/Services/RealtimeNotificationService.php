<?php

declare(strict_types=1);

namespace App\Services;

use App\Broadcasting\ChannelNames;
use App\Events\PrivateNotificationPushed;
use App\Events\PublicAnnouncementBroadcast;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\RealtimeNotification;

final class RealtimeNotificationService
{
    public function __construct(
        private readonly BroadcastService $broadcast,
    ) {}

    public function notifyUser(User $user, RealtimeNotification $notification): void
    {
        $this->deliverToUser($user, $notification);
    }

    public function notifyAdmin(Admin $admin, RealtimeNotification $notification): void
    {
        $this->deliverToAdmin($admin, $notification);
    }

    /**
     * @param  callable(Admin): RealtimeNotification  $factory
     */
    public function notifyEachAdmin(callable $factory): void
    {
        Admin::query()->each(function (Admin $admin) use ($factory): void {
            $this->notifyAdmin($admin, $factory($admin));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function broadcastPublicAnnouncement(
        string $title,
        string $message,
        array $data = [],
        ?string $actionUrl = null,
        ?string $tone = null,
    ): void {
        $this->broadcast->broadcast(new PublicAnnouncementBroadcast(
            title: $title,
            message: $message,
            data: $data,
            actionUrl: $actionUrl,
            tone: $tone,
        ));
    }

    public function verificationApproved(User $vendor, string $businessName, ?string $note = null): void
    {
        $this->notifyUser($vendor, RealtimeNotification::verificationApproved(
            recipientUserId: (int) $vendor->id,
            businessName: $businessName,
            note: $note,
        ));
    }

    public function verificationRevoked(User $vendor, string $businessName, ?string $reason = null): void
    {
        $this->notifyUser($vendor, RealtimeNotification::verificationRevoked(
            recipientUserId: $vendor->id,
            businessName: $businessName,
            reason: $reason,
        ));
    }

    public function verificationFlagged(User $vendor, string $businessName, string $reason): void
    {
        $this->notifyUser($vendor, RealtimeNotification::verificationFlagged(
            recipientUserId: (int) $vendor->id,
            businessName: $businessName,
            reason: $reason,
        ));
    }

    public function verificationSubmittedToAdmins(
        int $businessInfoId,
        string $businessName,
        string $vendorName,
    ): void {
        $this->notifyEachAdmin(fn(Admin $admin): RealtimeNotification => RealtimeNotification::verificationSubmitted(
            recipientAdminId: (int) $admin->id,
            businessInfoId: $businessInfoId,
            businessName: $businessName,
            vendorName: $vendorName,
        ));
    }

    public function paymentCompleted(User $user, string $purposeLabel, float $amount, string $currency): void
    {
        $this->notifyUser($user, RealtimeNotification::paymentCompleted(
            recipientUserId: (int) $user->id,
            purposeLabel: $purposeLabel,
            amount: $amount,
            currency: $currency,
        ));
    }

    public function newMessage(
        User $recipient,
        int $senderId,
        string $conversationUuid,
        string $senderName,
        string $preview,
        int $unreadCount,
        ?string $actionUrl = null,
        bool $fromPlatformAdmin = false,
    ): void {
        $notification = RealtimeNotification::newMessage(
            recipientUserId: (int) $recipient->id,
            senderId: $senderId,
            conversationUuid: $conversationUuid,
            senderName: $senderName,
            preview: $preview,
            unreadCount: $unreadCount,
            actionUrl: $actionUrl,
            fromPlatformAdmin: $fromPlatformAdmin,
        );

        $this->deliverToUser($recipient, $notification);
    }

    private function deliverToUser(User $user, RealtimeNotification $notification): void
    {
        if (! $user->wantsPushNotifications()) {
            return;
        }

        $user->notifyNow($notification);

        $this->pushPrivate(
            channelName: ChannelNames::user((int) $user->id),
            payload: $notification->toArray($user),
            eventName: $notification->broadcastAs(),
        );
    }

    private function deliverToAdmin(Admin $admin, RealtimeNotification $notification): void
    {
        $admin->notifyNow($notification);

        $this->pushPrivate(
            channelName: ChannelNames::admin((int) $admin->id),
            payload: $notification->toArray($admin),
            eventName: $notification->broadcastAs(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function pushPrivate(string $channelName, array $payload, string $eventName): void
    {
        $this->broadcast->broadcast(new PrivateNotificationPushed(
            channelName: $channelName,
            payload: $payload,
            eventName: $eventName,
        ));
    }
}
