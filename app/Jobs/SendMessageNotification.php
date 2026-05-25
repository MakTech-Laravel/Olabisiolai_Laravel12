<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Message;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Services\AdminMessagingUserResolver;
use App\Services\RealtimeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendMessageNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $messageId,
    ) {
    }

    public function handle(
        ConversationRepositoryInterface $conversationRepository,
        RealtimeNotificationService $realtimeNotifications,
    ): void {
        $message = Message::query()
            ->with(['sender', 'conversation.participantRows.user'])
            ->find($this->messageId);

        if ($message === null) {
            return;
        }

        $sender = $message->sender;

        if ($sender === null) {
            return;
        }

        foreach ($message->conversation->participantRows as $row) {
            if ((int) $row->user_id === (int) $sender->id) {
                continue;
            }

            $user = $row->user;

            if ($user === null) {
                continue;
            }

            $unread = $conversationRepository->unreadMessagesCountInConversation($user, $message->conversation);

            $preview = $message->body ?? '';

            if ($preview === '' && $message->attachments()->exists()) {
                $preview = '[Attachment]';
            }

            $adminMessagingUserIds = AdminMessagingUserResolver::messagingUserIds();
            $fromPlatformAdmin = in_array((int) $sender->id, $adminMessagingUserIds, true);

            $senderName = $fromPlatformAdmin
                ? (string) config('messaging.platform_admin_display_name', 'Olabisiolai Admin')
                : (string) $sender->name;

            $actionUrl = null;
            if ($fromPlatformAdmin && $user->isVendor()) {
                $actionUrl = (string) config('messaging.vendor_admin_message_url', '/vendor/leads?channel=admin');
            }

            $realtimeNotifications->newMessage(
                recipient: $user,
                senderId: (int) $sender->id,
                conversationUuid: (string) $message->conversation->uuid,
                senderName: $senderName,
                preview: mb_substr($preview, 0, 120),
                unreadCount: $unread,
                actionUrl: $actionUrl,
                fromPlatformAdmin: $fromPlatformAdmin,
            );
        }
    }
}
