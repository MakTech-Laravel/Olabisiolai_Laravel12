<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerifiedApi
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api') ?? $request->user('admin_api');

        if (($user instanceof User || $user instanceof Admin) && ! $user->email_verified_at) {
            return response()->json([
                'message' => 'Please verify your account before continuing.',
                'verification_status' => 'unverified',
            ], 403);
        }

        return $next($request);
    }
}
