<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class UpdateDeliveryStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
    ) {
    }

    public function handle(): void
    {
        Message::query()
            ->where('sender_id', '!=', $this->userId)
            ->where('status', MessageStatus::Sent)
            ->whereNull('deleted_at')
            ->whereHas('conversation.participantRows', function ($q): void {
                $q->where('user_id', $this->userId);
            })
            ->orderByDesc('id')
            ->limit(500)
            ->update(['status' => MessageStatus::Delivered]);
    }
}
