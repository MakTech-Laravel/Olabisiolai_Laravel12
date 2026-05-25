<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Messaging\MessageDTO;
use App\Enums\ParticipantRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class MessageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_message_persists_and_dispatches_side_effects(): void
    {
        Event::fake();
        Queue::fake();

        $a = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $b = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        $conversation = Conversation::factory()->create(['created_by' => $a->id]);
        foreach ([$a, $b] as $user) {
            ConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'role' => $user->id === $a->id ? ParticipantRole::Admin : ParticipantRole::Member,
            ]);
        }

        $dto = new MessageDTO(
            conversationUuid: (string) $conversation->uuid,
            body: 'Service hello',
            parentId: null,
            attachmentIds: [],
        );

        $this->actingAs($a, 'api');

        $message = app(MessageService::class)->sendMessage($dto, $a);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'conversation_id' => $conversation->id,
            'sender_id' => $a->id,
        ]);

        $conversation->refresh();
        $this->assertSame($message->id, $conversation->last_message_id);
    }
}
