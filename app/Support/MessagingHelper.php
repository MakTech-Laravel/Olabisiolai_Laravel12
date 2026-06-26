<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ConversationType;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\PresenceService;
use Illuminate\Support\Carbon;

final class MessagingHelper
{
    public static function sanitizeBody(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $trimmed = trim($body);

        if ($trimmed === '') {
            return null;
        }

        $stripped = strip_tags($trimmed);

        if (config('messaging.use_htmlpurifier', true) && class_exists(\HTMLPurifier::class)) {
            $config = \HTMLPurifier_Config::createDefault();

            return (new \HTMLPurifier($config))->purify($stripped);
        }

        return $stripped;
    }

    /**
     * Label shown for a participant (vendor → business name, user → personal name).
     */
    public static function participantDisplayName(?User $user): string
    {
        if ($user === null) {
            return 'Unknown';
        }

        if ($user->role === 'admin') {
            return (string) config('messaging.platform_admin_display_name', 'Olabisiolai Admin');
        }

        if ($user->role === 'vendor') {
            if ($user->relationLoaded('businessInfo') && filled($user->businessInfo?->business_name)) {
                return trim((string) $user->businessInfo->business_name);
            }
        }

        return self::userPersonalName($user);
    }

    /**
     * Secondary line shown when picking a message recipient (email, category, etc.).
     */
    public static function recipientSearchSubtitle(?User $user): string
    {
        if ($user === null) {
            return '';
        }

        if ($user->role === 'vendor') {
            if ($user->relationLoaded('businessInfo') && $user->businessInfo !== null) {
                $category = $user->businessInfo->relationLoaded('category')
                    ? trim((string) ($user->businessInfo->category?->name ?? ''))
                    : '';

                if ($category !== '') {
                    return $category;
                }
            }

            return 'Business';
        }

        $email = trim((string) $user->email);

        return $email !== '' ? $email : 'Member';
    }

    /**
     * Conversation title for the authenticated viewer (other party in direct chats).
     *
     * For business-tagged direct conversations:
     * - Business owner sees the sender's personal name (not their business name).
     * - Sender sees the contacted business name (not the business owner's personal name).
     */
    public static function conversationDisplayName(Conversation $conversation, ?User $viewer = null): string
    {
        if ($conversation->type === ConversationType::Direct && $viewer !== null) {
            if ($conversation->business_info_id !== null && $conversation->relationLoaded('businessInfo')) {
                $businessOwnerId = (int) ($conversation->businessInfo?->user_id ?? 0);

                if ($businessOwnerId > 0 && (int) $viewer->id === $businessOwnerId) {
                    // Business inbox: show the sender's personal name
                    $other = self::otherParticipant($conversation, $viewer);

                    return $other?->user !== null
                        ? self::userPersonalName($other->user)
                        : 'Direct message';
                }

                // Personal inbox: show the business name directly
                if (filled($conversation->businessInfo?->business_name)) {
                    return trim((string) $conversation->businessInfo->business_name);
                }
            }

            $other = self::otherParticipant($conversation, $viewer);

            if ($other?->user !== null) {
                return self::participantDisplayName($other->user);
            }

            return 'Direct message';
        }

        if (filled($conversation->name)) {
            return (string) $conversation->name;
        }

        return $conversation->type === ConversationType::Direct ? 'Direct message' : 'Conversation';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function conversationPeerSummary(Conversation $conversation, User $viewer): ?array
    {
        if ($conversation->type !== ConversationType::Direct) {
            return null;
        }

        $other = self::otherParticipant($conversation, $viewer);

        if ($other?->user === null) {
            return null;
        }

        $user = $other->user;

        $presence = $user->relationLoaded('messagingPresence') && $user->messagingPresence
            ? app(PresenceService::class)->toPublicPayload($user->messagingPresence)
            : null;

        if ($conversation->business_info_id !== null && $conversation->relationLoaded('businessInfo')) {
            $businessOwnerId = (int) ($conversation->businessInfo?->user_id ?? 0);

            if ($businessOwnerId > 0 && (int) $viewer->id === $businessOwnerId) {
                // Business inbox: show the sender's personal name and profile image
                $displayName = self::userPersonalName($user);
                $senderBusinessInfoId = null;
                if ($user->role === 'vendor' && $user->relationLoaded('businessInfo') && $user->businessInfo !== null) {
                    $senderBusinessInfoId = (int) $user->businessInfo->id;
                }

                return [
                    'user_id' => $other->user_id,
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'role' => $user->role,
                    'name' => $displayName,
                    'display_name' => $displayName,
                    'business_info_id' => $senderBusinessInfoId > 0 ? $senderBusinessInfoId : null,
                    'avatar_url' => $user->image_url,
                    'is_verified' => self::isVerifiedVendor($user),
                    'presence' => $presence,
                ];
            }

            // Personal inbox: show the contacted business name and logo
            if ($conversation->businessInfo !== null) {
                $businessInfo = $conversation->businessInfo;
                $businessName = trim((string) $businessInfo->business_name);
                $avatarUrl = filled($businessInfo->logo_path)
                    ? public_media_url((string) $businessInfo->logo_path, null)
                    : null;

                return [
                    'user_id' => $other->user_id,
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'role' => $user->role,
                    'name' => $businessName,
                    'display_name' => $businessName,
                    'business_info_id' => (int) $conversation->business_info_id,
                    'avatar_url' => $avatarUrl,
                    'is_verified' => $businessInfo->verified_at !== null,
                    'presence' => $presence,
                ];
            }
        }

        $displayName = self::participantDisplayName($user);
        $businessInfoId = null;
        if ($user->role === 'vendor' && $user->relationLoaded('businessInfo') && $user->businessInfo !== null) {
            $businessInfoId = (int) $user->businessInfo->id;
        }

        return [
            'user_id' => $other->user_id,
            'id' => $user->id,
            'uuid' => $user->uuid,
            'role' => $user->role,
            'name' => $displayName,
            'display_name' => $displayName,
            'business_info_id' => $businessInfoId > 0 ? $businessInfoId : null,
            'avatar_url' => self::userAvatarUrl($user),
            'is_verified' => self::isVerifiedVendor($user),
            'presence' => $presence,
        ];
    }

    public static function messagePreview(?Message $message): ?string
    {
        if ($message === null) {
            return null;
        }

        if (filled($message->body)) {
            return (string) $message->body;
        }

        if (
            $message->type === MessageType::Attachment
            || ($message->relationLoaded('attachments') && $message->attachments->isNotEmpty())
        ) {
            $first = $message->relationLoaded('attachments') ? $message->attachments->first() : null;
            $mime = $first?->mime_type ?? '';

            if (str_starts_with($mime, 'image/')) {
                return 'Photo';
            }

            if (str_starts_with($mime, 'video/')) {
                return 'Video';
            }

            if (str_starts_with($mime, 'audio/')) {
                return 'Audio';
            }

            return $first?->file_name ?? 'Attachment';
        }

        if ($message->type === MessageType::System) {
            return 'System message';
        }

        return 'Message';
    }

    public static function messageStatusForViewer(Message $message, ?User $viewer): string
    {
        $status = $message->status->value;

        if ($viewer === null || $message->sender_id !== $viewer->id) {
            return $status;
        }

        if ($message->relationLoaded('reads')) {
            $readByOther = $message->reads->contains(
                static fn ($read): bool => (int) $read->user_id !== (int) $viewer->id,
            );

            if ($readByOther) {
                return MessageStatus::Seen->value;
            }
        }

        return $status;
    }

    public static function messageStatusLabel(string $status, bool $isOwnMessage): ?string
    {
        if (! $isOwnMessage) {
            return null;
        }

        return match ($status) {
            MessageStatus::Seen->value => 'Seen',
            MessageStatus::Delivered->value => 'Delivered',
            MessageStatus::Sent->value => 'Sent',
            MessageStatus::Sending->value => 'Sending',
            default => null,
        };
    }

    public static function formatMessageTime(?Carbon $at): ?string
    {
        if ($at === null) {
            return null;
        }

        return $at->format('g:i A');
    }

    public static function userAvatarUrl(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($user->relationLoaded('businessInfo') && filled($user->businessInfo?->logo_path)) {
            return public_media_url((string) $user->businessInfo->logo_path, null);
        }

        return $user->image_url;
    }

    public static function isVerifiedVendor(?User $user): bool
    {
        if ($user === null || ! $user->relationLoaded('businessInfo')) {
            return false;
        }

        return $user->businessInfo?->verified_at !== null;
    }

    public static function userPersonalName(User $user): string
    {
        $name = trim((string) ($user->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $composed = trim(
            trim((string) ($user->first_name ?? '')).' '.trim((string) ($user->last_name ?? '')),
        );

        if ($composed !== '') {
            return $composed;
        }

        if (filled($user->email)) {
            return (string) strstr((string) $user->email, '@', true);
        }

        return 'User';
    }

    private static function otherParticipant(Conversation $conversation, User $viewer): ?ConversationParticipant
    {
        if (! $conversation->relationLoaded('participantRows')) {
            return null;
        }

        return $conversation->participantRows->first(
            static fn ($row): bool => (int) $row->user_id !== (int) $viewer->id,
        );
    }
}
