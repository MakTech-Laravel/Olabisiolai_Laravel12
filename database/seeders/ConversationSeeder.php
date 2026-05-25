<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\DTOs\Messaging\ConversationDTO;
use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Database\Seeder;

final class ConversationSeeder extends Seeder
{
    public function run(): void
    {
        Conversation::query()
            ->where('type', ConversationType::Direct)
            ->update(['name' => null]);

        $users = User::query()
            ->whereIn('role', ['user', 'vendor'])
            ->orderBy('id')
            ->take(10)
            ->get();

        if ($users->count() < 2) {
            return;
        }

        $service = app(ConversationService::class);

        for ($i = 0; $i < 5; $i++) {
            $a = $users[$i % $users->count()];
            $b = $users[($i + 1) % $users->count()];

            if ($a->id === $b->id) {
                continue;
            }

            $dto = new ConversationDTO(
                type: ConversationType::Direct,
                name: null,
                participantUserIds: [(int) $b->id],
            );

            $service->createConversation($dto, $a);
        }
    }
}
