<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\VendorAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VendorAnalyticsController extends Controller
{
    public function __construct(
        private readonly VendorAnalyticsService $vendorAnalyticsService,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user('api');

        if (! $user?->isVendor()) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'range' => ['sometimes', 'string', Rule::in(['30d', 'quarter', 'yearly'])],
        ]);

        try {
            $payload = $this->vendorAnalyticsService->getAnalytics(
                $user,
                $validated['range'] ?? '30d',
            );

            if (! ($payload['has_business'] ?? false)) {
                return sendResponse(false, 'No business profile found.', $payload, Response::HTTP_NOT_FOUND);
            }

            return sendResponse(true, 'Vendor analytics retrieved successfully.', $payload);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
