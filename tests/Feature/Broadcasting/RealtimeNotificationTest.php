<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Broadcasting\ChannelNames;
use App\Enums\RealtimeNotificationType;
use App\Events\PublicAnnouncementBroadcast;
use App\Notifications\RealtimeNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

final class RealtimeNotificationTest extends TestCase
{
    public function test_new_message_notification_broadcast_configuration(): void
    {
        $notification = RealtimeNotification::newMessage(
            recipientUserId: 5,
            senderId: 12,
            conversationUuid: 'conv-uuid',
            senderName: 'Jane',
            preview: 'Hello',
            unreadCount: 2,
        );

        $this->assertSame('new_message', $notification->broadcastAs());

        $channels = $notification->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-'.ChannelNames::user(5), $channels[0]->name);

        $payload = $notification->toArray(new \stdClass);
        $this->assertSame('conv-uuid', $payload['conversation_uuid']);
    }

    public function test_verification_approved_notification_payload(): void
    {
        $notification = RealtimeNotification::verificationApproved(
            recipientUserId: 9,
            businessName: 'Acme Ltd',
            note: 'All good',
        );

        $payload = $notification->toArray(new \stdClass);
        $this->assertSame(RealtimeNotificationType::VerificationApproved->value, $payload['type']);
        $this->assertSame('app.notification', $notification->broadcastAs());
    }

    public function test_admin_notification_uses_admin_channel(): void
    {
        $notification = RealtimeNotification::verificationSubmitted(
            recipientAdminId: 3,
            businessInfoId: 10,
            businessName: 'Shop',
            vendorName: 'Vendor',
        );

        $channels = $notification->broadcastOn();
        $this->assertSame('private-'.ChannelNames::admin(3), $channels[0]->name);
    }

    public function test_public_announcement_uses_public_channel(): void
    {
        $event = new PublicAnnouncementBroadcast(
            title: 'Maintenance',
            message: 'Scheduled downtime tonight.',
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame(ChannelNames::PUBLIC_ANNOUNCEMENTS, $channels[0]->name);
    }
}
