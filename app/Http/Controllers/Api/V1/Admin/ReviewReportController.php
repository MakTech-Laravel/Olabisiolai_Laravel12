<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReviewReportReason;
use App\Enums\ReviewReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReviewReportResource;
use App\Models\ReviewReport;
use App\Services\ReviewReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewReportController extends Controller
{
    public function __construct(
        private readonly ReviewReportService $reviewReportService
    ) {}

    /**
     * List all review reports with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'in:'.implode(',', ReviewReportStatus::values())],
            'reason' => ['nullable', 'string', 'in:'.implode(',', ReviewReportReason::values())],
            'review_id' => ['nullable', 'integer', 'exists:reviews,id'],
        ]);

        $reports = $this->reviewReportService->getReports($validated);

        return response()->json([
            'success' => true,
            'data' => ReviewReportResource::collection($reports->items()),
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * Show a single review report.
     */
    public function show(ReviewReport $reviewReport): JsonResponse
    {
        $reviewReport->load([
            'review.business:id,business_name',
            'user:id,first_name,last_name,email',
        ]);

        return response()->json([
            'success' => true,
            'data' => new ReviewReportResource($reviewReport),
        ]);
    }

    /**
     * Dismiss a report — no action needed on the review.
     */
    public function dismiss(ReviewReport $reviewReport): JsonResponse
    {
        $report = $this->reviewReportService->dismissReport($reviewReport);

        return response()->json([
            'success' => true,
            'data' => new ReviewReportResource($report),
            'message' => 'Report dismissed successfully.',
        ]);
    }

    /**
     * Resolve a report — action has been taken on the review.
     */
    public function resolve(ReviewReport $reviewReport): JsonResponse
    {
        $report = $this->reviewReportService->resolveReport($reviewReport);

        return response()->json([
            'success' => true,
            'data' => new ReviewReportResource($report),
            'message' => 'Report resolved successfully.',
        ]);
    }

    /**
     * Available report reasons for frontend dropdowns.
     */
    public function reasons(): JsonResponse
    {
        $reasons = array_map(
            fn (ReviewReportReason $reason) => [
                'value' => $reason->value,
                'label' => $reason->label(),
            ],
            ReviewReportReason::cases()
        );

        return response()->json([
            'success' => true,
            'data' => $reasons,
        ]);
    }
}
