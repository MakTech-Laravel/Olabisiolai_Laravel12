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
        private readonly RealtimeNotificationService $notifications,
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
     * @return array{following: bool, following_user_id: int, business_info_id: int, followers_count: int}
     */
    public function toggle(User $follower, User $target, int $businessInfoId): array
    {
        if ($follower->id === $target->id) {
            throw new RuntimeException('You cannot follow yourself.');
        }

        if (! $this->isFollowableVendor($target)) {
            throw new RuntimeException('Only vendor profiles can be followed.');
        }

        $business = BusinessInfo::query()
            ->whereKey($businessInfoId)
            ->where('user_id', $target->id)
            ->first();

        if (! $business instanceof BusinessInfo) {
            throw new RuntimeException('Business not found.');
        }

        /** @var UserFollow|null $existing */
        $existing = UserFollow::query()
            ->where('follower_id', $follower->id)
            ->where('business_info_id', $businessInfoId)
            ->first();

        if ($existing instanceof UserFollow) {
            $existing->delete();

            return [
                'following' => false,
                'following_user_id' => $target->id,
                'business_info_id' => $businessInfoId,
                'followers_count' => $this->followersCountForBusiness($businessInfoId),
            ];
        }

        UserFollow::query()->create([
            'follower_id' => $follower->id,
            'following_id' => $target->id,
            'business_info_id' => $businessInfoId,
        ]);

        $this->notifications->newFollow($target, $follower, $businessInfoId);

        return [
            'following' => true,
            'following_user_id' => $target->id,
            'business_info_id' => $businessInfoId,
            'followers_count' => $this->followersCountForBusiness($businessInfoId),
        ];
    }

    public function followersCountForBusiness(int $businessInfoId): int
    {
        return UserFollow::query()
            ->where('business_info_id', $businessInfoId)
            ->count();
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

    public function isFollowingBusiness(User $follower, int $businessInfoId): bool
    {
        return UserFollow::query()
            ->where('follower_id', $follower->id)
            ->where('business_info_id', $businessInfoId)
            ->exists();
    }
}
