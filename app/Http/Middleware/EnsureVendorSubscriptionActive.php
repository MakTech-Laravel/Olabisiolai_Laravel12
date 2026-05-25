<?php

namespace App\Http\Middleware;

use App\Services\BusinessInfoService;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorSubscriptionActive
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
            return $next($request);
        }

        if ($this->subscriptionService->canAccessVendorFeatures($business)) {
            return $next($request);
        }

        return sendResponse(
            false,
            'Complete your premium subscription payment to access vendor features.',
            [
                'subscription' => $this->subscriptionService->subscriptionPayload($business),
                'payment_required' => true,
            ],
            Response::HTTP_PAYMENT_REQUIRED,
        );
    }
}
