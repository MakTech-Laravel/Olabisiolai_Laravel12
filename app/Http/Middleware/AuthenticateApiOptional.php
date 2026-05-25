<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate API users when a Bearer token is present, without rejecting guests.
 */
class AuthenticateApiOptional
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() && ! $request->user('api')) {
            try {
                auth('api')->authenticate();
            } catch (\Throwable) {
                // Invalid or expired token — continue as guest.
            }
        }

        return $next($request);
    }
}
