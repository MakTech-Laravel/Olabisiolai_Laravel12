<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ConversationType;
use App\Enums\ParticipantRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Laravel\Passport\Passport;

final class ConversationTest extends MessagingTestCase
{
    public function test_user_can_create_list_show_search_and_delete_conversation(): void
    {
        $a = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $b = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($a, guard: 'api');

        $create = $this->postJson('/api/v1/conversations', [
            'type' => ConversationType::Direct->value,
            'participants' => [$b->uuid],
        ]);

        $create->assertCreated();
        $uuid = $create->json('data.uuid');
        $this->assertNotEmpty($uuid);

        $index = $this->getJson('/api/v1/conversations');
        $index->assertOk();
        $this->assertGreaterThanOrEqual(1, count($index->json('data')));

        $show = $this->getJson('/api/v1/conversations/'.$uuid);
        $show->assertOk();
        $show->assertJsonPath('data.uuid', $uuid);

        $search = $this->getJson('/api/v1/conversations/search?q=direct');
        $search->assertOk();

        $delete = $this->deleteJson('/api/v1/conversations/'.$uuid);
        $delete->assertOk();
    }

    public function test_non_participant_cannot_view_conversation(): void
    {
        $a = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $b = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $c = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        $conversation = Conversation::factory()->create(['created_by' => $a->id]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $a->id,
            'role' => ParticipantRole::Admin,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $b->id,
            'role' => ParticipantRole::Member,
        ]);

        Passport::actingAs($c, guard: 'api');

        $this->getJson('/api/v1/conversations/'.$conversation->uuid)
            ->assertForbidden();
    }
}
