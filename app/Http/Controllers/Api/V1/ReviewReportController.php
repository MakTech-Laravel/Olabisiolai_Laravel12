<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewReportRequest;
use App\Http\Resources\Api\V1\ReviewReportResource;
use App\Models\Review;
use App\Services\ReviewReportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ReviewReportController extends Controller
{
    public function __construct(
        private readonly ReviewReportService $reviewReportService
    ) {}

    /**
     * Report reason options for the public report-abuse UI.
     */
    public function reasons(): JsonResponse
    {
        $reasons = array_map(
            fn(ReviewReportReason $reason) => [
                'value' => $reason->value,
                'label' => $reason->label(),
            ],
            ReviewReportReason::cases()
        );

        return sendResponse(true, 'Report reasons retrieved successfully.', [
            'reasons' => $reasons,
        ]);
    }

    /**
     * Report a review for abuse.
     */
    public function store(StoreReviewReportRequest $request, Review $review): Response
    {
        $user = $request->user('api');

        try {
            $report = $this->reviewReportService->storeReport($review, $user, $request->validated());

            return sendResponse(true, 'Thank you for your report. Our team will review it shortly.', [
                'report' => new ReviewReportResource($report),
            ], Response::HTTP_CREATED);
        } catch (\RuntimeException $e) {
            return sendResponse(false, $e->getMessage(), null, Response::HTTP_CONFLICT);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
