<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessReportRequest;
use App\Http\Resources\Api\V1\BusinessReportResource;
use App\Models\BusinessInfo;
use App\Services\BusinessReportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BusinessReportController extends Controller
{
    public function __construct(
        private readonly BusinessReportService $businessReportService,
    ) {}

    public function reasons(): JsonResponse
    {
        $reasons = array_map(
            fn(ReviewReportReason $reason) => [
                'value' => $reason->value,
                'label' => $reason->label(),
            ],
            array_filter(
                ReviewReportReason::cases(),
                fn(ReviewReportReason $reason) => $reason !== ReviewReportReason::Other,
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
