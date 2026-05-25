<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Message;
use App\Models\User;
use App\Support\MessagingHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $viewer */
        $viewer = $request->user('api');
        $status = MessagingHelper::messageStatusForViewer($this->resource, $viewer);
        $isOwn = $viewer !== null && (int) $this->sender_id === (int) $viewer->id;

        return [
            'uuid' => $this->uuid,
            'conversation_id' => $this->conversation_id,
            'sender' => $this->whenLoaded('sender', fn() => [
                'id' => $this->sender?->id,
                'name' => $this->sender?->name,
                'avatar_url' => MessagingHelper::userAvatarUrl($this->sender),
            ]),
            'parent_uuid' => $this->parent_id !== null
                ? ($this->relationLoaded('parent') ? $this->parent?->uuid : null)
                : null,
            'parent' => $this->whenLoaded('parent', function () {
                if ($this->parent === null) {
                    return null;
                }

                return [
                    'uuid' => $this->parent->uuid,
                    'conversation_id' => $this->parent->conversation_id,
                    'body' => $this->parent->body,
                    'type' => $this->parent->type->value,
                    'created_at' => $this->parent->created_at?->toIso8601String(),
                    'sender' => $this->parent->relationLoaded('sender') ? [
                        'id' => $this->parent->sender?->id,
                        'name' => $this->parent->sender?->name,
                        'avatar_url' => MessagingHelper::userAvatarUrl($this->parent->sender),
                    ] : null,
                    'attachments' => $this->parent->relationLoaded('attachments')
                        ? AttachmentResource::collection($this->parent->attachments)
                        : [],
                ];
            }),
            'body' => $this->body,
            'type' => $this->type->value,
            'status' => $status,
            'status_label' => MessagingHelper::messageStatusLabel($status, $isOwn),
            'is_own' => $isOwn,
            'edited_at' => $this->edited_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_formatted' => MessagingHelper::formatMessageTime($this->created_at),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'read_by' => $this->whenLoaded('reads', fn() => $this->reads->pluck('user_id')->values()->all()),
        ];
    }
}
