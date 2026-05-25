<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Maps an {@see Admin} to a {@see User} row used as a messaging participant.
 */
final class AdminMessagingUserResolver
{
    public static function resolve(Admin $admin): User
    {
        $email = strtolower(trim((string) $admin->email));

        $existing = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $name = trim((string) ($admin->name ?? 'Platform Admin'));

        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'first_name' => Str::before($name, ' ') ?: 'Admin',
            'last_name' => Str::after($name, ' ') ?: '',
            'name' => $name !== '' ? $name : 'Platform Admin',
            'email' => $admin->email,
            'password' => bcrypt(Str::random(32)),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @return list<int>
     */
    public static function messagingUserIds(): array
    {
        $emails = Admin::query()->pluck('email')->map(
            static fn ($email) => strtolower(trim((string) $email)),
        )->filter()->unique()->values()->all();

        if ($emails === []) {
            return [];
        }

        return User::query()
            ->where(function ($query) use ($emails): void {
                foreach ($emails as $email) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }
}
