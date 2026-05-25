<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

final class BroadcastService
{
    /**
     * Dispatch a broadcast without failing HTTP/DB work when Reverb is down or misconfigured.
     * Events using {@see \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow} otherwise throw
     * inside transactions (e.g. message send) and surface as 500.
     */
    public function broadcast(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            Log::warning('Broadcast skipped: '.$e->getMessage(), [
                'event' => $event::class,
                'exception' => $e,
            ]);
        }
    }
}
