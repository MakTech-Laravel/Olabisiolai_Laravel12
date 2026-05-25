<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PresenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class UpdateUserStatus
{
    public function __construct(
        private readonly PresenceService $presence,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if ($user instanceof User) {
            try {
                if ($request->routeIs('api.v1.presence.offline')) {
                    $this->presence->markOffline($user);
                } else {
                    $this->presence->markOnline($user);
                }
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
