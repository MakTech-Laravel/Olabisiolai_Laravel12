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
            ->with(['sender', 'conversation.participantRows.user', 'conversation.businessInfo'])
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

            $conversation = $message->conversation;
            $conversationUuid = (string) $conversation->uuid;
            $businessInfoId = $conversation->business_info_id;

            $actionUrl = null;
            if ($fromPlatformAdmin && $user->isVendor()) {
                $actionUrl = (string) config('messaging.vendor_admin_message_url', '/vendor/leads?channel=admin');
            } elseif ($businessInfoId) {
                $businessOwnerId = $conversation->businessInfo?->user_id;
                if ($businessOwnerId !== null && (int) $businessOwnerId === (int) $user->id) {
                    $actionUrl = sprintf(
                        '/user/messages?business_id=%d&c=%s',
                        (int) $businessInfoId,
                        rawurlencode($conversationUuid),
                    );
                } else {
                    $actionUrl = sprintf(
                        '/user/messages?scope=personal&c=%s',
                        rawurlencode($conversationUuid),
                    );
                }
            }

            $realtimeNotifications->newMessage(
                recipient: $user,
                senderId: (int) $sender->id,
                conversationUuid: $conversationUuid,
                senderName: $senderName,
                preview: mb_substr($preview, 0, 120),
                unreadCount: $unread,
                actionUrl: $actionUrl,
                fromPlatformAdmin: $fromPlatformAdmin,
                businessInfoId: $businessInfoId ? (int) $businessInfoId : null,
            );
        }
    }
}
