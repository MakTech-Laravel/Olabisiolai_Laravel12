<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTrustedDevice;
use Illuminate\Support\Str;

class TrustedDeviceService
{
    public function hashDeviceId(string $deviceId): string
    {
        return hash('sha256', Str::lower(trim($deviceId)));
    }

    public function isTrusted(User $user, string $deviceId): bool
    {
        if (trim($deviceId) === '') {
            return false;
        }

        return UserTrustedDevice::query()
            ->where('user_id', $user->id)
            ->where('device_id_hash', $this->hashDeviceId($deviceId))
            ->exists();
    }

    public function remember(User $user, string $deviceId, ?string $label = null): UserTrustedDevice
    {
        $hash = $this->hashDeviceId($deviceId);
        $now = now();

        /** @var UserTrustedDevice $device */
        $device = UserTrustedDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id_hash' => $hash,
            ],
            [
                'label' => $label ? Str::limit(trim($label), 255, '') : null,
                'last_used_at' => $now,
            ],
        );

        return $device;
    }

    public function touch(User $user, string $deviceId): void
    {
        if (trim($deviceId) === '') {
            return;
        }

        UserTrustedDevice::query()
            ->where('user_id', $user->id)
            ->where('device_id_hash', $this->hashDeviceId($deviceId))
            ->update(['last_used_at' => now()]);
    }
}
