<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PresenceUserStatus;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class PresenceService
{
    public function setOnline(User $user): void
    {
        $this->upsertStatus($user, PresenceUserStatus::Online, now());
    }

    public function setOffline(User $user): void
    {
        $this->upsertStatus($user, PresenceUserStatus::Offline, now());
    }

    public function setAway(User $user): void
    {
        $this->upsertStatus($user, PresenceUserStatus::Away, now());
    }

    public function updateLastSeen(User $user): void
    {
        DB::table('user_statuses')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, UserStatus>
     */
    public function getOnlineUsers(array $userIds): Collection
    {
        if ($userIds === []) {
            return new Collection;
        }

        return UserStatus::query()
            ->whereIn('user_id', $userIds)
            ->where('status', PresenceUserStatus::Online->value)
            ->get();
    }

    private function upsertStatus(User $user, PresenceUserStatus $status, ?Carbon $lastSeen): void
    {
        DB::table('user_statuses')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'status' => $status->value,
                'last_seen_at' => $lastSeen,
                'updated_at' => now(),
            ]
        );
    }
}
