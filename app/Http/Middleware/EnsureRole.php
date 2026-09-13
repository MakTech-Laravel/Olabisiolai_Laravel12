<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * One or more allowed roles may be passed as middleware arguments:
     *   e.g. middleware('role:admin') or middleware('role:user,vendor')
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user('api');

        if (! $user instanceof User || ! in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if ($user->isSuspendedOrBlocked()) {
            return response()->json([
                'success' => false,
                'message' => $user->status === UserStatus::Suspended
                    ? 'Account suspended. Please contact support.'
                    : 'This account has been blocked. Please contact support.',
                'data' => [
                    'account_status' => $user->status instanceof UserStatus ? $user->status->value : $user->status,
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
