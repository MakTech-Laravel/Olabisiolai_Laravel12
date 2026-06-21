<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\BusinessStatus;
use App\Enums\ConversationType;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\BusinessInfo;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
     * Personal name shown in direct messages (never a business listing title).
     */
    public static function participantPersonalName(?User $user): string
    {
        if ($user === null) {
            return 'Unknown';
        }

        if ($user->role === 'admin') {
            return (string) config('messaging.platform_admin_display_name', 'Olabisiolai Admin');
        }

        return self::userPersonalName($user);
    }

    /**
     * Primary business listing name when the user owns one (search subtitle / context).
     */
    public static function participantBusinessName(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($user->relationLoaded('businessInfo') && filled($user->businessInfo?->business_name)) {
            $name = trim((string) $user->businessInfo->business_name);

            return $name !== '' ? $name : null;
        }

        if ($user->relationLoaded('businessInfos') && $user->businessInfos->isNotEmpty()) {
            $first = $user->businessInfos->first();
            if ($first instanceof BusinessInfo && filled($first->business_name)) {
                $name = trim((string) $first->business_name);

                return $name !== '' ? $name : null;
            }
        }

        return null;
    }

    /**
     * @deprecated Prefer {@see participantPersonalName()} for direct chats.
     */
    public static function participantDisplayName(?User $user): string
    {
        return self::participantPersonalName($user);
    }

    /**
     * Secondary line shown when picking a message recipient (email, category, etc.).
     */
    public static function recipientSearchSubtitle(?User $user): string
    {
        if ($user === null) {
            return '';
        }

        $businessName = self::participantBusinessName($user);
        if ($businessName !== null) {
            if ($user->relationLoaded('businessInfo') && $user->businessInfo !== null) {
                $category = $user->businessInfo->relationLoaded('category')
                    ? trim((string) ($user->businessInfo->category?->name ?? ''))
                    : '';

                if ($category !== '') {
                    return $category;
                }
            }

            return $businessName;
        }

        $email = trim((string) $user->email);

        return $email !== '' ? $email : 'Member';
    }

    /**
     * Conversation title for the authenticated viewer (other party in direct chats).
     */
    public static function conversationDisplayName(Conversation $conversation, ?User $viewer = null): string
    {
        if ($conversation->type === ConversationType::Direct && $viewer !== null) {
            $other = self::otherParticipant($conversation, $viewer);

            if ($other?->user !== null) {
                return self::directConversationTitleForViewer($conversation, $viewer, $other->user);
            }

            return 'Direct message';
        }

        if (filled($conversation->name)) {
            return (string) $conversation->name;
        }

        return $conversation->type === ConversationType::Direct ? 'Direct message' : 'Conversation';
    }

    public static function conversationImageUrl(Conversation $conversation, ?User $viewer): ?string
    {
        if ($conversation->type !== ConversationType::Direct || $viewer === null) {
            return null;
        }

        $other = self::otherParticipant($conversation, $viewer);

        if ($other?->user === null) {
            return null;
        }

        if (self::viewerOwnsConversationListing($conversation, $viewer)) {
            return self::userPersonalAvatarUrl($other->user);
        }

        $listing = self::resolveDirectListingBusiness($conversation, $other->user);
        if ($listing !== null) {
            return public_media_url($listing->logo_path);
        }

        return self::userPersonalAvatarUrl($other->user);
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
        $personalName = self::participantPersonalName($user);
        $businessName = self::participantBusinessName($user);
        $listing = self::resolveDirectListingBusiness($conversation, $user);
        $ownsListing = self::viewerOwnsConversationListing($conversation, $viewer);

        $displayName = $ownsListing
            ? $personalName
            : (filled($listing?->business_name)
                ? trim((string) $listing->business_name)
                : $personalName);

        $businessInfoId = $listing !== null ? (int) $listing->id : null;

        return [
            'user_id' => $other->user_id,
            'id' => $user->id,
            'uuid' => $user->uuid,
            'role' => $user->role,
            'personal_name' => $personalName,
            'name' => $displayName,
            'display_name' => $displayName,
            'business_name' => $businessName,
            'business_info_id' => $businessInfoId > 0 ? $businessInfoId : null,
            'avatar_url' => $ownsListing
                ? self::userPersonalAvatarUrl($user)
                : (public_media_url($listing?->logo_path) ?? self::userPersonalAvatarUrl($user)),
            'is_verified' => $listing?->verified_at !== null || self::isVerifiedVendor($user),
            'owned_businesses' => self::ownedBusinessesSummary($user),
            'presence' => $user->relationLoaded('messagingPresence') && $user->messagingPresence
                ? app(\App\Services\PresenceService::class)->toPublicPayload($user->messagingPresence)
                : null,
        ];
    }

    public static function recipientAvatarUrl(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($user->relationLoaded('businessInfo') && $user->businessInfo !== null) {
            $logo = public_media_url($user->businessInfo->logo_path);
            if ($logo !== null && $logo !== '') {
                return $logo;
            }
        }

        return self::userPersonalAvatarUrl($user);
    }

    public static function recipientDisplayName(?User $user): string
    {
        return self::participantBusinessName($user) ?? self::participantPersonalName($user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function ownedBusinessesSummary(User $user): array
    {
        /** @var Collection<int, BusinessInfo> $businesses */
        $businesses = $user->relationLoaded('businessInfos')
            ? $user->businessInfos
            : collect();

        if ($businesses->isEmpty() && $user->relationLoaded('businessInfo') && $user->businessInfo instanceof BusinessInfo) {
            $businesses = collect([$user->businessInfo]);
        }

        return $businesses
            ->filter(static fn(BusinessInfo $business): bool => $business->business_status === BusinessStatus::Active && ! $business->is_flagged)
            ->map(static function (BusinessInfo $business): array {
                $categoryName = $business->relationLoaded('category') && $business->category
                    ? trim((string) $business->category->name)
                    : null;
                $location = $business->relationLoaded('location') && $business->location
                    ? trim((string) $business->location->full_name)
                    : null;

                return [
                    'id' => $business->id,
                    'business_name' => $business->business_name,
                    'logo_url' => public_media_url($business->logo_path),
                    'category_name' => $categoryName !== '' ? $categoryName : null,
                    'location' => $location !== '' ? $location : null,
                ];
            })
            ->values()
            ->all();
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
                static fn($read): bool => (int) $read->user_id !== (int) $viewer->id,
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

    public static function userPersonalAvatarUrl(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        return $user->image_url;
    }

    /**
     * @deprecated Prefer {@see userPersonalAvatarUrl()} in direct messaging UI.
     */
    public static function userAvatarUrl(?User $user): ?string
    {
        return self::userPersonalAvatarUrl($user);
    }

    public static function isVerifiedVendor(?User $user): bool
    {
        if ($user === null || ! $user->relationLoaded('businessInfo')) {
            return false;
        }

        return $user->businessInfo?->verified_at !== null;
    }

    private static function userPersonalName(User $user): string
    {
        $name = trim((string) ($user->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $composed = trim(
            trim((string) ($user->first_name ?? '')) . ' ' . trim((string) ($user->last_name ?? '')),
        );

        if ($composed !== '') {
            return $composed;
        }

        if (filled($user->email)) {
            return (string) strstr((string) $user->email, '@', true);
        }

        return 'User';
    }

    private static function directConversationTitleForViewer(
        Conversation $conversation,
        User $viewer,
        User $otherUser,
    ): string {
        if (self::viewerOwnsConversationListing($conversation, $viewer)) {
            return self::participantPersonalName($otherUser);
        }

        $listing = self::resolveDirectListingBusiness($conversation, $otherUser);
        if ($listing !== null && filled($listing->business_name)) {
            return trim((string) $listing->business_name);
        }

        return self::participantPersonalName($otherUser);
    }

    private static function viewerOwnsConversationListing(Conversation $conversation, User $viewer): bool
    {
        $listing = self::resolveDirectListingBusiness($conversation);

        return $listing !== null && (int) $listing->user_id === (int) $viewer->id;
    }

    private static function resolveDirectListingBusiness(
        Conversation $conversation,
        ?User $peerUser = null,
    ): ?BusinessInfo {
        if ($conversation->relationLoaded('businessInfo') && $conversation->businessInfo instanceof BusinessInfo) {
            return $conversation->businessInfo;
        }

        if ($conversation->business_info_id !== null && (int) $conversation->business_info_id > 0) {
            $loaded = BusinessInfo::query()->find((int) $conversation->business_info_id);

            return $loaded instanceof BusinessInfo ? $loaded : null;
        }

        if ($peerUser === null) {
            return null;
        }

        if ($peerUser->relationLoaded('businessInfo') && $peerUser->businessInfo instanceof BusinessInfo) {
            $business = $peerUser->businessInfo;
            if ($business->business_status === BusinessStatus::Active && ! $business->is_flagged) {
                return $business;
            }
        }

        if ($peerUser->relationLoaded('businessInfos')) {
            $fromCollection = $peerUser->businessInfos->first(
                static fn(BusinessInfo $business): bool => $business->business_status === BusinessStatus::Active && ! $business->is_flagged,
            );

            if ($fromCollection instanceof BusinessInfo) {
                return $fromCollection;
            }
        }

        $loaded = BusinessInfo::query()
            ->where('user_id', $peerUser->id)
            ->where('business_status', BusinessStatus::Active)
            ->where('is_flagged', false)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        return $loaded instanceof BusinessInfo ? $loaded : null;
    }

    /**
     * @return \App\Models\ConversationParticipant|null
     */
    private static function otherParticipant(Conversation $conversation, User $viewer): ?\App\Models\ConversationParticipant
    {
        if (! $conversation->relationLoaded('participantRows')) {
            return null;
        }

        return $conversation->participantRows->first(
            static fn($row): bool => (int) $row->user_id !== (int) $viewer->id,
        );
    }
}
