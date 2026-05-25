<?php

declare(strict_types=1);

namespace App\Events;

use App\Broadcasting\ChannelNames;
use App\Enums\RealtimeNotificationType;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Platform-wide public channel broadcast (no auth required to subscribe).
 */
final class PublicAnnouncementBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly array $data = [],
        public readonly ?string $actionUrl = null,
        public readonly ?string $tone = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel(ChannelNames::PUBLIC_ANNOUNCEMENTS)];
    }

    public function broadcastAs(): string
    {
        return 'app.notification';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => RealtimeNotificationType::SystemAnnouncement->value,
            'title' => $this->title,
            'message' => $this->message,
            'tone' => $this->tone ?? RealtimeNotificationType::SystemAnnouncement->defaultTone(),
            'action_url' => $this->actionUrl,
            'data' => $this->data,
        ];
    }
}
