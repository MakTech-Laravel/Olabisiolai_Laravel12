<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PresenceUserStatus;
use App\Events\ConversationUserPresenceUpdated;
use App\Events\UserPresenceUpdated;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class PresenceService
{
    public function __construct(
        private readonly BroadcastService $broadcast,
    ) {}

    /**
     * @return array{status: string, last_seen_at: string|null}|null
     */
    public function toPublicPayload(?UserStatus $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $effective = $this->effectiveStatus($row);

        return [
            'status' => $effective->value,
            'last_seen_at' => $row->last_seen_at?->toIso8601String()
                ?? $row->updated_at?->toIso8601String(),
        ];
    }

    public function effectiveStatus(UserStatus $row): PresenceUserStatus
    {
        if ($row->status !== PresenceUserStatus::Online) {
            return $row->status;
        }

        $staleSeconds = max(30, (int) config('messaging.presence_stale_seconds', 120));
        $updatedAt = $row->updated_at;

        if ($updatedAt !== null && $updatedAt->lt(now()->subSeconds($staleSeconds))) {
            return PresenceUserStatus::Offline;
        }

        return PresenceUserStatus::Online;
    }

    public function markOnline(User $user): void
    {
        $this->setOnline($user);
        $this->broadcastUserPresence($user, PresenceUserStatus::Online, now());
        $this->broadcastToUserConversations($user, PresenceUserStatus::Online, now());
    }

    public function markOffline(User $user): void
    {
        $lastSeen = now();
        $this->setOffline($user);
        $this->broadcastUserPresence($user, PresenceUserStatus::Offline, $lastSeen);
        $this->broadcastToUserConversations($user, PresenceUserStatus::Offline, $lastSeen);
    }
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

    private function broadcastUserPresence(
        User $user,
        PresenceUserStatus $status,
        ?Carbon $lastSeenAt,
    ): void {
        try {
            $this->broadcast->broadcast(new UserPresenceUpdated($user, $status, $lastSeenAt));
        } catch (\Throwable) {
            // Never block HTTP responses on broadcast failures.
        }
    }

    private function broadcastToUserConversations(
        User $user,
        PresenceUserStatus $status,
        ?Carbon $lastSeenAt,
    ): void {
        $conversationIds = DB::table('conversation_participants')
            ->where('user_id', $user->id)
            ->pluck('conversation_id');

        foreach ($conversationIds as $conversationId) {
            try {
                $this->broadcast->broadcast(new ConversationUserPresenceUpdated(
                    (int) $conversationId,
                    (int) $user->id,
                    $status,
                    $lastSeenAt,
                ));
            } catch (\Throwable) {
                continue;
            }
        }
    }
}
