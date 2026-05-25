<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\PresenceUserStatus;
use App\Events\UserPresenceUpdated;
use App\Jobs\UpdateDeliveryStatus;
use Illuminate\Support\Facades\Cache;

final class HandleUserPresence
{
    public function handle(UserPresenceUpdated $event): void
    {
        if ($event->status !== PresenceUserStatus::Online) {
            return;
        }

        if (Cache::add('messaging:delivery-dispatch:'.$event->user->id, true, 60)) {
            UpdateDeliveryStatus::dispatch($event->user->id);
        }
    }
}
