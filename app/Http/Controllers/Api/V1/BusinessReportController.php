<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessReportRequest;
use App\Http\Resources\Api\V1\BusinessReportResource;
use App\Models\BusinessInfo;
use App\Services\BusinessReportService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BusinessReportController extends Controller
{
    public function __construct(
        private readonly BusinessReportService $businessReportService,
    ) {}

    #[OA\Get(
        path: '/v1/business-report-reasons',
        summary: 'List reasons available for reporting a business',
        tags: ['Public'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reasons retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'reasons', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'value', type: 'string'),
                            new OA\Property(property: 'label', type: 'string'),
                        ], type: 'object')),
                    ], type: 'object'),
                ]),
            ),
        ],
    )]
    #[OA\Get(
        path: '/v1/review-report-reasons',
        summary: 'List reasons available for reporting a review',
        description: 'Shares the same handler/response shape as GET /v1/business-report-reasons.',
        tags: ['Public'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reasons retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'reasons', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'value', type: 'string'),
                            new OA\Property(property: 'label', type: 'string'),
                        ], type: 'object')),
                    ], type: 'object'),
                ]),
            ),
        ],
    )]
    public function reasons(): JsonResponse
    {
        $reasons = array_map(
            fn (ReviewReportReason $reason) => [
                'value' => $reason->value,
                'label' => $reason->label(),
            ],
            array_filter(
                ReviewReportReason::cases(),
                fn (ReviewReportReason $reason) => $reason !== ReviewReportReason::Other,
            ),
        );

        return sendResponse(true, 'Report reasons retrieved successfully.', [
            'reasons' => array_values($reasons),
        ]);
    }

    public function store(StoreBusinessReportRequest $request, BusinessInfo $businessInfo): Response
    {
        $user = $request->user('api');

        try {
            $report = $this->businessReportService->storeReport(
                $businessInfo,
                $user,
                $request->validated(),
            );

            return sendResponse(
                true,
                'Thank you for your report. Our team will review it shortly.',
                ['report' => new BusinessReportResource($report)],
                Response::HTTP_CREATED,
            );
        } catch (\RuntimeException $e) {
            return sendResponse(false, $e->getMessage(), null, Response::HTTP_CONFLICT);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
