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
            fn (ReviewReportReason $reason) => [
                'value' => $reason->value,
                'label' => $reason->label(),
            ],
            ReviewReportReason::forReviewReports()
        );

        return sendResponse(true, 'Report reasons retrieved successfully.', [
            'reasons' => $reasons,
        ]);
    }

    /**
     * Report a review for abuse (customer or vendor).
     */
    public function store(StoreReviewReportRequest $request, Review $review): Response
    {
        $user = $request->user('api');

        try {
            $report = $user->isVendor()
                ? $this->reviewReportService->storeVendorReport($review, $user, $request->validated())
                : $this->reviewReportService->storeReport($review, $user, $request->validated());

            return sendResponse(true, 'Thank you for your report. Our team will review it shortly.', [
                'report' => new ReviewReportResource($report),
            ], Response::HTTP_CREATED);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            $status = str_contains(strtolower($message), 'already reported')
                ? Response::HTTP_CONFLICT
                : (str_contains(strtolower($message), 'only') || str_contains(strtolower($message), 'cannot')
                    ? Response::HTTP_FORBIDDEN
                    : Response::HTTP_UNPROCESSABLE_ENTITY);

            return sendResponse(false, $message, null, $status);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
