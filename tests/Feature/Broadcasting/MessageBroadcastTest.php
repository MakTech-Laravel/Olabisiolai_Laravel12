<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Enums\ParticipantRole;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MessageBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_sent_event_broadcast_configuration(): void
    {
        $user = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        $conversation = Conversation::factory()->create(['created_by' => $user->id]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => ParticipantRole::Admin,
        ]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => 'Broadcast me',
        ]);

        $message->load('sender');

        $event = new MessageSent($message);

        $this->assertSame('message.sent', $event->broadcastAs());

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);

        $payload = $event->broadcastWith();
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('sender', $payload);
        $this->assertSame($message->conversation_id, $payload['conversation_id']);
    }
}
