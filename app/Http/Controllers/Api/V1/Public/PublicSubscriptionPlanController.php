<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\PricingPackageService;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Unauthenticated subscription plan list for the public pricing/landing page.
 */
class PublicSubscriptionPlanController extends Controller
{
    public function __construct(
        private readonly PricingPackageService $pricingPackageService,
    ) {}

    #[OA\Get(
        path: '/v1/subscription-packages',
        summary: 'List active Premium subscription plans',
        tags: ['Public'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription packages retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'currency', type: 'string', example: 'NGN'),
                        new OA\Property(property: 'packages', type: 'array', items: new OA\Items(type: 'object')),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function index()
    {
        try {
            return sendResponse(true, 'Subscription packages retrieved successfully.', [
                'currency' => $this->pricingPackageService->subscriptionCurrency(),
                'packages' => $this->pricingPackageService->subscriptionPackages(),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
