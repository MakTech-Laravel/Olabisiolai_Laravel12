<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class UserService
{
    public function paginateUsers(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return User::query()
            ->when(isset($filters['role']), function ($query) use ($filters) {
                $query->where('role', $filters['role']);
            })
            ->when(isset($filters['status']), function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $keyword = trim((string) $filters['search']);

                if ($keyword === '') {
                    return;
                }

                $query->where(function ($nestedQuery) use ($keyword) {
                    $nestedQuery
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%");
                });
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getUserById(int $userId): User
    {
        return User::query()->findOrFail($userId);
    }

    public function getUserByIdForAdmin(int $userId): User
    {
        return User::query()
            ->with([
                'businessInfo.category',
                'businessInfo.location',
                'businessInfo.subscription',
                'businessInfo.boost',
            ])
            ->findOrFail($userId);
    }

    public function changeStatus(User $user, string $status): User
    {
        $user->update(['status' => $status]);

        return $user->fresh();
    }

    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    public function deleteUser(User $user): void
    {
        $user->delete();
    }

    /**
     * @return array{
     *     all_users: int,
     *     total_users: int,
     *     total_vendors: int,
     *     total_admins: int,
     *     new_signups: int
     * }
     */
    public function getUserManagementSummaryStatistics(?Carbon $now = null): array
    {
        $now ??= now();
        $since = $now->copy()->subHours(48);

        $base = User::query();

        return [
            'all_users' => (clone $base)->count(),
            'total_users' => (clone $base)->where('role', 'user')->count(),
            'total_vendors' => (clone $base)->where('role', 'vendor')->count(),
            'total_admins' => (clone $base)->where('role', 'admin')->count(),
            'new_signups' => (clone $base)->where('created_at', '>=', $since)->count(),
        ];
    }
}
