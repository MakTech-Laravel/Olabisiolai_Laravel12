<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Seeder;

final class MessageSeeder extends Seeder
{
    public function run(): void
    {
        Conversation::query()->each(function (Conversation $conversation): void {
            $participantIds = $conversation->participantRows()->pluck('user_id')->all();

            if ($participantIds === []) {
                return;
            }

            for ($i = 0; $i < 50; $i++) {
                Message::factory()->create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $participantIds[$i % count($participantIds)],
                    'body' => 'Seeded message '.($i + 1),
                ]);
            }

            $last = $conversation->messages()->latest('id')->first();

            if ($last !== null) {
                $conversation->forceFill(['last_message_id' => $last->id])->save();
            }
        });
    }
}
