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
        $this->notifyEachAdmin(fn (Admin $admin): RealtimeNotification => RealtimeNotification::verificationSubmitted(
            recipientAdminId: (int) $admin->id,
            businessInfoId: $businessInfoId,
            businessName: $businessName,
            vendorName: $vendorName,
        ));
    }

    public function newFollow(User $vendor, User $follower, int $businessInfoId): void
    {
        $followerBusinessId = $follower->isVendor() && $follower->businessInfo !== null
            ? (int) $follower->businessInfo->id
            : null;

        $this->notifyUser($vendor, RealtimeNotification::newFollow(
            recipientUserId: (int) $vendor->id,
            recipientBusinessId: $businessInfoId,
            followerId: (int) $follower->id,
            followerName: (string) $follower->name,
            followerUuid: (string) $follower->uuid,
            followerRole: (string) $follower->role,
            followerBusinessId: $followerBusinessId,
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

    public function referralRewardsPaid(User $referrer, User $invitee, float $amount, ?string $currency = null): void
    {
        $currency = $currency ?? (string) config('subscription.currency', 'NGN');
        $formattedAmount = strtoupper($currency) === 'NGN'
            ? '₦'.number_format($amount, 0)
            : $currency.' '.number_format($amount, 2);

        $this->notifyUser($referrer, RealtimeNotification::referralRewardPaid(
            recipientUserId: (int) $referrer->id,
            role: 'referrer',
            amount: $amount,
            currency: $currency,
            counterpartyName: trim((string) $invitee->name) !== '' ? (string) $invitee->name : 'your invitee',
            formattedAmount: $formattedAmount,
        ));

        $this->notifyUser($invitee, RealtimeNotification::referralRewardPaid(
            recipientUserId: (int) $invitee->id,
            role: 'invitee',
            amount: $amount,
            currency: $currency,
            counterpartyName: trim((string) $referrer->name) !== '' ? (string) $referrer->name : 'your referrer',
            formattedAmount: $formattedAmount,
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
        ?int $businessInfoId = null,
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
            businessInfoId: $businessInfoId,
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
