<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\AttachmentResource;
use App\Models\Message;
use App\Support\MessagingHelper;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
    ) {
        $this->message->loadMissing([
            'sender.businessInfo:id,user_id,logo_path,verified_at',
            'parent.sender.businessInfo:id,user_id,logo_path,verified_at',
            'parent.attachments',
            'attachments',
        ]);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.' . $this->message->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $sender = $this->message->sender;
        $parent = $this->message->parent;

        return [
            'message' => [
                'uuid' => $this->message->uuid,
                'body' => $this->message->body,
                'type' => $this->message->type->value,
                'status' => $this->message->status->value,
                'conversation_id' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_id,
                'parent_id' => $this->message->parent_id,
                'parent_uuid' => $parent?->uuid,
                'parent' => $parent !== null ? [
                    'uuid' => $parent->uuid,
                    'conversation_id' => $parent->conversation_id,
                    'body' => $parent->body,
                    'type' => $parent->type->value,
                    'created_at' => $parent->created_at?->toIso8601String(),
                    'sender' => [
                        'id' => $parent->sender?->id,
                        'name' => $parent->sender?->name,
                        'avatar_url' => MessagingHelper::userAvatarUrl($parent->sender),
                    ],
                    'attachments' => $parent->relationLoaded('attachments')
                        ? AttachmentResource::collection($parent->attachments)->resolve()
                        : [],
                ] : null,
                'attachments' => $this->message->relationLoaded('attachments')
                    ? AttachmentResource::collection($this->message->attachments)->resolve()
                    : [],
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
            'sender' => [
                'id' => $sender?->id,
                'name' => $sender?->name,
                'avatar_url' => MessagingHelper::userAvatarUrl($sender),
            ],
            'conversation_id' => $this->message->conversation_id,
        ];
    }
}
