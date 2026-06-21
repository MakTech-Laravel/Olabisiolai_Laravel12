<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Models\BusinessInfo;
use App\Models\User;
use App\Models\UserFollow;
use RuntimeException;

class UserFollowService
{
    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotifications,
    ) {}

    public function canManageFollows(?User $user): bool
    {
        return $user instanceof User && ($user->isUser() || $user->isVendor());
    }

    public function isFollowableVendor(User $target): bool
    {
        if (! $target->isVendor()) {
            return false;
        }

        return BusinessInfo::query()
            ->where('user_id', $target->id)
            ->where('business_status', BusinessStatus::Active->value)
            ->where('is_flagged', false)
            ->exists();
    }

    /**
     * @return array{following: bool, following_user_id: int}
     */
    public function toggle(User $follower, User $target): array
    {
        if ($follower->id === $target->id) {
            throw new RuntimeException('You cannot follow yourself.');
        }

        if (! $this->isFollowableVendor($target)) {
            throw new RuntimeException('Only vendor profiles can be followed.');
        }

        /** @var UserFollow|null $existing */
        $existing = UserFollow::query()
            ->where('follower_id', $follower->id)
            ->where('following_id', $target->id)
            ->first();

        if ($existing instanceof UserFollow) {
            $existing->delete();

            return [
                'following' => false,
                'following_user_id' => $target->id,
            ];
        }

        UserFollow::query()->create([
            'follower_id' => $follower->id,
            'following_id' => $target->id,
        ]);

        $this->notifyNewFollower($follower, $target);

        return [
            'following' => true,
            'following_user_id' => $target->id,
        ];
    }

    private function notifyNewFollower(User $follower, User $target): void
    {
        $business = BusinessInfo::query()
            ->where('user_id', $target->id)
            ->where('business_status', BusinessStatus::Active->value)
            ->where('is_flagged', false)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        $businessName = is_string($business?->business_name) && trim($business->business_name) !== ''
            ? trim($business->business_name)
            : 'your business';

        $followerName = trim((string) ($follower->name ?? ''));
        if ($followerName === '') {
            $followerName = 'Someone';
        }

        $this->realtimeNotifications->newFollower(
            recipient: $target,
            followerId: (int) $follower->id,
            followerName: $followerName,
            businessInfoId: (int) ($business?->id ?? 0),
            businessName: $businessName,
        );
    }

    public function followersCount(int $userId): int
    {
        return UserFollow::query()->where('following_id', $userId)->count();
    }

    public function followingCount(int $userId): int
    {
        return UserFollow::query()->where('follower_id', $userId)->count();
    }

    public function isFollowing(User $follower, int $targetUserId): bool
    {
        return UserFollow::query()
            ->where('follower_id', $follower->id)
            ->where('following_id', $targetUserId)
            ->exists();
    }
}
