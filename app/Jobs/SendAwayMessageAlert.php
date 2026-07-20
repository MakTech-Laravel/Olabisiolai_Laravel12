<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PresenceUserStatus;
use App\Models\Message;
use App\Models\User;
use App\Services\AdminMessagingUserResolver;
use App\Services\PresenceService;
use App\Services\VendorAwayMessageNotifier;
use App\Support\MessagingHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class SendAwayMessageAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $messageId,
    ) {}

    public function handle(
        PresenceService $presence,
        VendorAwayMessageNotifier $notifier,
    ): void {
        $message = Message::query()
            ->with(['sender', 'conversation.participantRows.user.messagingPresence', 'conversation.participantRows.user.businessInfo', 'conversation.businessInfo'])
            ->find($this->messageId);

        if ($message === null) {
            return;
        }

        $sender = $message->sender;

        if ($sender === null) {
            return;
        }

        $conversation = $message->conversation;
        $adminMessagingUserIds = AdminMessagingUserResolver::messagingUserIds();
        $fromPlatformAdmin = in_array((int) $sender->id, $adminMessagingUserIds, true);

        $senderName = $fromPlatformAdmin
            ? (string) config('messaging.platform_admin_display_name', 'Olabisiolai Admin')
            : MessagingHelper::userPersonalName($sender);

        foreach ($conversation->participantRows as $row) {
            if ((int) $row->user_id === (int) $sender->id) {
                continue;
            }

            if ($row->is_muted) {
                continue;
            }

            $user = $row->user;

            if ($user === null || ! $user->isVendor()) {
                continue;
            }

            if (! $this->shouldSendOutbound($user)) {
                continue;
            }

            if ($this->isEffectivelyOnline($user, $presence)) {
                continue;
            }

            $debounceMinutes = max(1, (int) config('messaging.away_alert_debounce_minutes', 15));
            $cacheKey = sprintf('away_alert:%d:%d', $user->id, $conversation->id);

            if (! Cache::add($cacheKey, true, now()->addMinutes($debounceMinutes))) {
                continue;
            }

            $actionUrl = $this->resolveActionUrl(
                user: $user,
                conversationUuid: (string) $conversation->uuid,
                businessInfoId: $conversation->business_info_id ? (int) $conversation->business_info_id : null,
                businessOwnerId: $conversation->businessInfo?->user_id !== null
                    ? (int) $conversation->businessInfo->user_id
                    : null,
                fromPlatformAdmin: $fromPlatformAdmin,
            );

            $notifier->notify($user, $senderName, $actionUrl);
        }
    }

    private function shouldSendOutbound(User $user): bool
    {
        return $user->wantsEmailNotifications()
            || $user->wantsSmsNotifications()
            || $user->wantsWhatsappNotifications();
    }

    private function isEffectivelyOnline(User $user, PresenceService $presence): bool
    {
        $row = $user->messagingPresence;

        if ($row === null) {
            return false;
        }

        return $presence->effectiveStatus($row) === PresenceUserStatus::Online;
    }

    private function resolveActionUrl(
        User $user,
        string $conversationUuid,
        ?int $businessInfoId,
        ?int $businessOwnerId,
        bool $fromPlatformAdmin,
    ): string {
        if ($fromPlatformAdmin && $user->isVendor()) {
            return (string) config('messaging.vendor_admin_message_url', '/vendor/leads?channel=admin');
        }

        if ($businessInfoId !== null) {
            if ($businessOwnerId !== null && $businessOwnerId === (int) $user->id) {
                return sprintf(
                    '/user/messages?business_id=%d&c=%s',
                    $businessInfoId,
                    rawurlencode($conversationUuid),
                );
            }

            return sprintf(
                '/user/messages?scope=personal&c=%s',
                rawurlencode($conversationUuid),
            );
        }

        return sprintf(
            '/user/messages?scope=personal&c=%s',
            rawurlencode($conversationUuid),
        );
    }
}
