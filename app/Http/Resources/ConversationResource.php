<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\User;
use App\Support\MessagingHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
final class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $viewer */
        $viewer = $request->user('api');
        $lastMessage = $this->relationLoaded('lastMessage') ? $this->lastMessage : null;
        $displayName = MessagingHelper::conversationDisplayName($this->resource, $viewer);
        $peer = $viewer !== null
            ? MessagingHelper::conversationPeerSummary($this->resource, $viewer)
            : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'name' => $this->name,
            'display_name' => $displayName,
            'conversation_name' => $displayName,
            'conversation_image_url' => $peer['avatar_url'] ?? null,
            'is_archived' => $this->is_archived,
            'business_info_id' => $this->business_info_id,
            'tenant_id' => $this->tenant_id,
            'unread_count' => (int) ($this->unread_count ?? 0),
            'has_unread' => (int) ($this->unread_count ?? 0) > 0,
            'last_message_preview' => MessagingHelper::messagePreview($lastMessage),
            'last_message_at' => $lastMessage?->created_at?->toIso8601String(),
            'peer' => $this->when($viewer !== null, fn () => $peer),
            'last_message' => new MessageResource($this->whenLoaded('lastMessage')),
            'participants' => $this->whenLoaded('participantRows', function () {
                return $this->participantRows->map(function ($row): array {
                    $user = $row->relationLoaded('user') ? $row->user : $row->user()->first();

                    return [
                        'user_id' => $row->user_id,
                        'role' => $row->role->value,
                        'joined_at' => $row->joined_at?->toIso8601String(),
                        'last_read_at' => $row->last_read_at?->toIso8601String(),
                        'is_muted' => $row->is_muted,
                        'user' => $user ? [
                            'id' => $user->id,
                            'uuid' => $user->uuid,
                            'name' => MessagingHelper::participantDisplayName($user),
                            'display_name' => MessagingHelper::participantDisplayName($user),
                            'avatar_url' => MessagingHelper::userAvatarUrl($user),
                            'is_verified' => MessagingHelper::isVerifiedVendor($user),
                            'presence' => $user->relationLoaded('messagingPresence') && $user->messagingPresence
                                ? (new UserStatusResource($user->messagingPresence))->resolve()
                                : null,
                        ] : null,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
