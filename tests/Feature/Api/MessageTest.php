<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ParticipantRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

final class MessageTest extends MessagingTestCase
{
    public function test_user_can_send_edit_delete_read_and_type_in_conversation(): void
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

        Passport::actingAs($a, guard: 'api');

        $send = $this->postJson('/api/v1/conversations/'.$conversation->uuid.'/messages', [
            'body' => 'Hello world',
        ]);
        $send->assertCreated();
        $messageUuid = $send->json('data.uuid');

        $list = $this->getJson('/api/v1/conversations/'.$conversation->uuid.'/messages');
        $list->assertOk();

        $patch = $this->patchJson('/api/v1/messages/'.$messageUuid, [
            'body' => 'Hello updated',
        ]);
        $patch->assertOk();

        Passport::actingAs($b, guard: 'api');

        $this->postJson('/api/v1/messages/'.$messageUuid.'/read')->assertOk();

        Passport::actingAs($a, guard: 'api');

        $this->postJson('/api/v1/conversations/'.$conversation->uuid.'/typing', [
            'is_typing' => true,
        ])->assertOk();

        $this->deleteJson('/api/v1/messages/'.$messageUuid)->assertOk();
    }

    public function test_non_member_cannot_send_message(): void
    {
        Queue::fake();
        Event::fake();

        $a = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $b = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        $conversation = Conversation::factory()->create(['created_by' => $a->id]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $a->id,
            'role' => ParticipantRole::Admin,
        ]);

        Passport::actingAs($b, guard: 'api');

        $this->postJson('/api/v1/conversations/'.$conversation->uuid.'/messages', [
            'body' => 'Nope',
        ])->assertForbidden();
    }
}
