<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Services\BusinessInfoService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VendorOnboardingController extends Controller
{
    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function status(Request $request)
    {
        try {
            $vendor = $request->user('api');
            $business = $this->businessInfoService->findForUser($vendor);

            if ($business === null && $vendor->isVendor()) {
                try {
                    $business = $this->businessInfoService->createFreeTemplateForUser($vendor);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }

            return sendResponse(
                true,
                'Vendor onboarding status retrieved successfully.',
                $this->subscriptionService->onboardingPayload($business),
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
