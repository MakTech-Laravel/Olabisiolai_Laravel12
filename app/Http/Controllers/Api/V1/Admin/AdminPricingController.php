<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\PricingPackageService;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminPricingController extends Controller
{
    public function __construct(
        private readonly PricingPackageService $pricingPackageService,
    ) {}

    public function index()
    {
        return sendResponse(true, 'Pricing packages retrieved successfully.', [
            'packages' => $this->pricingPackageService->allForAdmin(),
        ]);
    }

    public function updateVerification(Request $request)
    {
        return $this->updatePackages($request, 'verification');
    }

    public function updateSubscription(Request $request)
    {
        return $this->updatePackages($request, 'subscription');
    }

    private function updatePackages(Request $request, string $type): Response
    {
        try {
            $validated = $request->validate([
                'packages' => ['required', 'array', 'min:1'],
                'packages.*.package_key' => ['required', 'string', 'max:50'],
                'packages.*.title' => ['required', 'string', 'max:120'],
                'packages.*.amount' => ['required', 'integer', 'min:1'],
                'packages.*.description' => ['nullable', 'string', 'max:2000'],
                'packages.*.perks' => ['nullable', 'array'],
                'packages.*.perks.*' => ['string', 'max:255'],
                'packages.*.is_active' => ['sometimes', 'boolean'],
                'packages.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            ]);

            if ($type === 'verification') {
                $allowedKeys = ['individual', 'business', 'ltd'];
                foreach ($validated['packages'] as $package) {
                    if (! in_array($package['package_key'], $allowedKeys, true)) {
                        throw new RuntimeException('Invalid verification package key.');
                    }
                }

                $this->pricingPackageService->syncVerificationPackages($validated['packages']);
            } else {
                $this->pricingPackageService->syncSubscriptionPackages($validated['packages']);
            }

            return sendResponse(true, ucfirst($type) . ' pricing updated successfully.', [
                'packages' => $this->pricingPackageService->allForAdmin(),
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
