<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ReviewController extends Controller
{
    public function __construct(
        private ReviewService $reviewService
    ) {}

    /**
     * Display approved reviews, optionally scoped to a business.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:business_info,id',
            'business_id' => 'nullable|integer|exists:business_info,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'rating' => 'integer|min:1|max:5',
            'sort' => 'nullable|in:recent,top',
        ]);

        // Support both 'id' and 'business_id' parameters
        $businessId = $validated['business_id'] ?? $validated['id'] ?? null;

        $filters = array_merge($validated, ['is_approved' => true]);
        if ($businessId) {
            $filters['business_id'] = $businessId;
        }

        $reviews = $this->reviewService->getReviews($filters);

        $payload = [
            'success' => true,
            'data' => ReviewResource::collection($reviews->items()),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ];

        if ($businessId) {
            $payload['summary'] = $this->reviewService->getBusinessReviewsSummary((int) $businessId);
        }

        return response()->json($payload);
    }

    /**
     * Submit a new review for a business.
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Pass files directly to service for processing
        $review = $this->reviewService->storeReview(
            $validated,
            $request->user('api'),
            $this->collectUploadedImages($request),
        );

        return response()->json([
            'success' => true,
            'data' => new ReviewResource($review),
            'message' => 'Review submitted successfully',
        ], 201);
    }

    /**
     * @return list<UploadedFile>
     */
    private function collectUploadedImages(Request $request): array
    {
        $files = $request->file('images');

        if ($files instanceof UploadedFile) {
            return $files->isValid() ? [$files] : [];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter(
            $files,
            static fn (mixed $file): bool => $file instanceof UploadedFile && $file->isValid(),
        ));
    }
}
