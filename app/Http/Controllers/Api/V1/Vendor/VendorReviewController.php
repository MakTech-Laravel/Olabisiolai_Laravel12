<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReviewReplyResource;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Services\ReviewReplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorReviewController extends Controller
{
    public function __construct(
        private ReviewReplyService $reviewReplyService
    ) {}

    /**
     * Get all reviews for vendor's business with replies.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'rating' => 'integer|min:1|max:5',
            'has_reply' => 'boolean',
            'search' => 'string|max:255',
        ]);

        $reviews = $this->reviewReplyService->getVendorBusinessReviews(
            $request->user(),
            $validated
        );

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
     * Get a single review with its replies.
     */
    public function show(Review $review, Request $request): JsonResponse
    {
        // Verify vendor owns the business being reviewed
        $business = $request->user()->businessInfo;
        if (! $business || $business->id !== $review->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this review',
            ], 403);
        }

        $review->load([
            'images',
            'user:id,first_name,last_name,email',
            'replies' => function ($query) {
                $query->with(['vendor:id,first_name,last_name,email'])->oldest('created_at');
            },
        ]);

        return response()->json([
            'success' => true,
            'data' => new ReviewResource($review),
        ]);
    }

    /**
     * Add a reply to a review.
     */
    public function reply(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'reply_text' => 'required|string|max:1000',
        ]);

        // Verify vendor owns the business being reviewed
        $business = $request->user()->businessInfo;
        if (! $business || $business->id !== $review->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to reply to this review',
            ], 403);
        }

        $reply = $this->reviewReplyService->storeReply([
            'review_id' => $review->id,
            'reply_text' => $validated['reply_text'],
        ], $request->user());

        return response()->json([
            'success' => true,
            'data' => new ReviewReplyResource($reply),
            'message' => 'Reply added successfully',
        ], 201);
    }

    /**
     * Update a reply.
     */
    public function updateReply(Request $request, ReviewReply $reply): JsonResponse
    {
        $validated = $request->validate([
            'reply_text' => 'required|string|max:1000',
        ]);

        $reply = $this->reviewReplyService->updateReply($reply, $validated, $request->user());

        return response()->json([
            'success' => true,
            'data' => new ReviewReplyResource($reply),
            'message' => 'Reply updated successfully',
        ]);
    }

    /**
     * Delete a reply.
     */
    public function deleteReply(Request $request, ReviewReply $reply): JsonResponse
    {
        $this->reviewReplyService->deleteReply($reply, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Reply deleted successfully',
        ]);
    }

    /**
     * Get vendor's review statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->reviewReplyService->getVendorReviewStats($request->user());

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get replies for a specific review.
     */
    public function replies(Review $review, Request $request): JsonResponse
    {
        // Verify vendor owns the business being reviewed
        $business = $request->user()->businessInfo;
        if (! $business || $business->id !== $review->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view replies for this review',
            ], 403);
        }

        $replies = $this->reviewReplyService->getReviewReplies($review);

        return response()->json([
            'success' => true,
            'data' => ReviewReplyResource::collection($replies),
        ]);
    }
}
