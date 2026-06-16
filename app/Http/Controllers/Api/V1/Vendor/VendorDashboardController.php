<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BusinessInfoService;
use App\Services\VendorDashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VendorDashboardController extends Controller
{
    public function __construct(
        private readonly VendorDashboardService $vendorDashboardService,
        private readonly BusinessInfoService $businessInfoService,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user('api');

        if (! $user?->isVendor()) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $businessId = $request->integer('business_id') ?: null;
            if ($businessId !== null) {
                $this->businessInfoService->assertUserOwnsBusiness($user, $businessId);
            }

            $payload = $this->vendorDashboardService->getDashboard($user, $businessId);

            if (! ($payload['has_business'] ?? false)) {
                return sendResponse(false, 'No business profile found.', $payload, Response::HTTP_NOT_FOUND);
            }

            return sendResponse(true, 'Vendor dashboard retrieved successfully.', $payload);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
