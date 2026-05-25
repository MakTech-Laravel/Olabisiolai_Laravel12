<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Conversation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class ThrottleMessaging
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if ($user === null) {
            return $next($request);
        }

        $conversation = $request->route('conversation');

        $conversationId = $conversation instanceof Conversation ? $conversation->id : 0;

        $perMinute = (int) config('messaging.message_rate_limit_per_minute', 60);

        $key = sprintf('messaging:%d:%d', $user->id, $conversationId);

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many messages. Please slow down.',
                'data' => null,
                'errors' => null,
                'meta' => null,
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
