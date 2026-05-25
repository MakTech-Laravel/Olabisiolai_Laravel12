<?php

namespace App\Http\Middleware;

use App\Services\BusinessInfoService;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorPremiumActive
{
    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
        private readonly SubscriptionService $subscriptionService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $vendor = $request->user('api');

        if ($vendor === null) {
            return $next($request);
        }

        $business = $this->businessInfoService->findForUser($vendor);

        if ($business === null) {
            return sendResponse(false, 'No business profile found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($this->subscriptionService->hasActivePremium($business)) {
            return $next($request);
        }

        return sendResponse(
            false,
            'An active premium subscription is required for this feature.',
            [
                'subscription' => $this->subscriptionService->subscriptionPayload($business),
                'premium_required' => true,
            ],
            Response::HTTP_FORBIDDEN,
        );
    }
}
