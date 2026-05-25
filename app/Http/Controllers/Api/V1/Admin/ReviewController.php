<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private ReviewService $reviewService
    ) {}

    /**
     * List all reviews with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'business_id' => 'nullable|integer|exists:business_info,id',
            'rating' => 'integer|min:1|max:5',
            'is_approved' => 'boolean',
            'is_flagged' => 'nullable|boolean',
            'search' => 'string|max:255',
        ]);

        $reviews = $this->reviewService->getReviews($validated);

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($reviews),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Show a single review with full details.
     */
    public function show(Review $review): JsonResponse
    {
        $review->load(['images', 'user:id,first_name,last_name,email', 'business:id,business_name']);

        return response()->json([
            'success' => true,
            'data' => new ReviewResource($review),
        ]);
    }

    /**
     * Update review approval/flag status.
     */
    public function update(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'is_approved' => 'nullable|boolean',
            'flag_reason' => 'required_if:is_approved,false|nullable|string|max:1000',
        ]);

        // Do not use array_filter(..., fn => !== null): it is easy to accidentally drop
        // boolean `false` for is_approved on some stacks; build explicitly so `false` is kept.
        $data = [];
        if (array_key_exists('is_approved', $validated)) {
            $data['is_approved'] = (bool) $validated['is_approved'];
        }
        if (array_key_exists('flag_reason', $validated)) {
            $data['flag_reason'] = $validated['flag_reason'];
        }

        $review = $this->reviewService->updateReview($review, $data);

        return response()->json([
            'success' => true,
            'data' => new ReviewResource($review),
            'message' => 'Review updated successfully',
        ]);
    }

    /**
     * Delete a review and its images.
     */
    public function destroy(Review $review): JsonResponse
    {
        $this->reviewService->deleteReview($review);

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully',
        ]);
    }

    /**
     * Bulk approve multiple reviews.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'review_ids' => 'required|array',
            'review_ids.*' => 'integer|exists:reviews,id',
        ]);

        $count = $this->reviewService->bulkApprove($validated['review_ids']);

        return response()->json([
            'success' => true,
            'message' => "{$count} reviews approved successfully",
        ]);
    }

    /**
     * Bulk flag multiple reviews.
     */
    public function bulkFlag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'review_ids' => 'required|array',
            'review_ids.*' => 'integer|exists:reviews,id',
            'flag_reason' => 'required|string|max:1000',
        ]);

        $count = $this->reviewService->bulkFlag($validated['review_ids'], $validated['flag_reason']);

        return response()->json([
            'success' => true,
            'message' => "{$count} reviews flagged successfully",
        ]);
    }

    /**
     * Get aggregate review statistics for the dashboard.
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->reviewService->getStatistics();
        $stats['pending_business_reports'] = app(\App\Services\BusinessReportService::class)->pendingCount();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
