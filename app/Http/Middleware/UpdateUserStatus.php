<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\PresenceUserStatus;
use App\Events\UserPresenceUpdated;
use App\Models\User;
use App\Services\BroadcastService;
use App\Services\PresenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class UpdateUserStatus
{
    public function __construct(
        private readonly PresenceService $presence,
        private readonly BroadcastService $broadcast,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if ($user instanceof User) {
            try {
                $this->presence->setOnline($user);
                $this->broadcast->broadcast(
                    new UserPresenceUpdated($user, PresenceUserStatus::Online, now()),
                );
            } catch (\Throwable $exception) {
                // Presence updates must never block API responses (e.g. messaging list).
                Log::warning('Presence broadcast failed', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $next($request);
    }
}
