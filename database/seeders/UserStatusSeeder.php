<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PresenceUserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class UserStatusSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->orderBy('id')->each(function (User $user, int $index): void {
            $status = $index % 3 === 0 ? PresenceUserStatus::Online : PresenceUserStatus::Offline;

            DB::table('user_statuses')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'status' => $status->value,
                    'last_seen_at' => now()->subMinutes($index),
                    'updated_at' => now(),
                ]
            );
        });
    }
}
