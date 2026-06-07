<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePurchasesEmailVerified
{
    /**
     * Block purchases when the user has an email on file that is not verified.
     * Phone-only accounts without an email may still purchase.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user('api');

        if ($user instanceof User && ! $user->canMakePurchases()) {
            return sendResponse(
                false,
                'Please verify your email address before making a purchase.',
                [
                    'email_verification_required' => true,
                    'email' => $user->email,
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
