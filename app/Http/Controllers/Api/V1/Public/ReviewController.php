<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use OpenApi\Attributes as OA;

class ReviewController extends Controller
{
    public function __construct(
        private ReviewService $reviewService
    ) {}

    #[OA\Post(
        path: '/v1/reviews',
        summary: 'List approved reviews, optionally scoped to a business',
        description: 'Public, unauthenticated. Uses POST (not GET) so filters can be sent as a JSON body.',
        tags: ['Public'],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'id', type: 'integer', nullable: true, description: 'Alias for business_id.'),
                new OA\Property(property: 'business_id', type: 'integer', nullable: true),
                new OA\Property(property: 'page', type: 'integer', minimum: 1, nullable: true),
                new OA\Property(property: 'per_page', type: 'integer', minimum: 1, maximum: 100, nullable: true),
                new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5, nullable: true),
                new OA\Property(property: 'sort', type: 'string', enum: ['recent', 'top'], nullable: true),
            ]),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reviews retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'pagination', properties: [
                        new OA\Property(property: 'current_page', type: 'integer'),
                        new OA\Property(property: 'last_page', type: 'integer'),
                        new OA\Property(property: 'per_page', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer'),
                    ], type: 'object'),
                    new OA\Property(property: 'summary', type: 'object', nullable: true, description: 'Present only when business_id/id is provided.'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
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

    #[OA\Post(
        path: '/v1/reviews/store',
        summary: 'Submit a new review for a business',
        tags: ['Public'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['business_id', 'rating', 'review_text'],
                    properties: [
                        new OA\Property(property: 'business_id', type: 'integer'),
                        new OA\Property(property: 'full_name', type: 'string', maxLength: 255, nullable: true),
                        new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5),
                        new OA\Property(property: 'review_text', type: 'string', minLength: 10, maxLength: 2000),
                        new OA\Property(
                            property: 'images',
                            type: 'array',
                            nullable: true,
                            maxItems: 10,
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'Up to 10 images (jpeg/jpg/png/webp, max 5MB each).',
                        ),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Review submitted successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'object'),
                    new OA\Property(property: 'message', type: 'string'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
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

        if (is_array($files)) {
            return array_values(array_filter(
                $files,
                static fn (mixed $file): bool => $file instanceof UploadedFile && $file->isValid(),
            ));
        }

        $normalized = [];
        foreach ($request->allFiles() as $key => $value) {
            if ($key === 'images' || str_starts_with((string) $key, 'images.')) {
                if ($value instanceof UploadedFile && $value->isValid()) {
                    $normalized[] = $value;
                } elseif (is_array($value)) {
                    foreach ($value as $file) {
                        if ($file instanceof UploadedFile && $file->isValid()) {
                            $normalized[] = $file;
                        }
                    }
                }
            }
        }

        return $normalized;
    }
}
