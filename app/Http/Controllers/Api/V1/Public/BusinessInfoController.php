<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BusinessInfoResource;
use App\Services\BoostCampaignAnalyticsService;
use App\Services\BusinessInfoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Public marketplace business listing & detail.
 * Domain logic lives in {@see BusinessInfoService} (section: “Public marketplace API”).
 */
class BusinessInfoController extends Controller
{
    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
        private readonly BoostCampaignAnalyticsService $boostCampaignAnalytics,
    ) {}

    #[OA\Get(
        path: '/v1/businesses/home',
        summary: 'List businesses for the home page',
        description: 'Public, unauthenticated. Returns active businesses (verified badge shown only when verification is approved).',
        tags: ['Public'],
        parameters: [
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'subcategory', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 255)),
            new OA\Parameter(name: 'location_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'lat', in: 'query', required: false, description: 'Requires lng.', schema: new OA\Schema(type: 'number', format: 'float', minimum: -90, maximum: 90)),
            new OA\Parameter(name: 'lng', in: 'query', required: false, description: 'Requires lat.', schema: new OA\Schema(type: 'number', format: 'float', minimum: -180, maximum: 180)),
            new OA\Parameter(name: 'radius_km', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float', minimum: 1, maximum: 200)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 255)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 50, default: 12)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'featured', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Businesses retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'filter', type: 'object'),
                        new OA\Property(property: 'count', type: 'integer'),
                        new OA\Property(property: 'pagination', properties: [
                            new OA\Property(property: 'current_page', type: 'integer'),
                            new OA\Property(property: 'per_page', type: 'integer'),
                            new OA\Property(property: 'last_page', type: 'integer'),
                            new OA\Property(property: 'total', type: 'integer'),
                        ], type: 'object'),
                        new OA\Property(property: 'businesses', type: 'array', items: new OA\Items(type: 'object')),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function homePage(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_id' => ['nullable', 'integer', 'exists:categories,id'],
                'subcategory' => ['nullable', 'string', 'max:255'],
                'location_id' => ['nullable', 'integer', 'exists:locations,id'],
                'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
                'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
                'radius_km' => ['nullable', 'numeric', 'min:1', 'max:200'],
                'search' => ['nullable', 'string', 'max:255'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
                'page' => ['nullable', 'integer', 'min:1'],
                'featured' => ['nullable', 'boolean'],
            ]);

            $perPage = $validated['per_page'] ?? 12;
            $businesses = $this->businessInfoService->paginatePublicHomePage(
                $validated,
                $request->user('api'),
                $perPage
            );

            if ($businesses->total() === 0) {
                return sendResponse(true, 'No businesses found.', [
                    'filter' => [
                        'category_id' => $validated['category_id'] ?? null,
                        'location_id' => $validated['location_id'] ?? null,
                        'search' => $validated['search'] ?? null,
                        'featured' => $validated['featured'] ?? false,
                    ],
                    'count' => 0,
                    'pagination' => [
                        'current_page' => $businesses->currentPage(),
                        'per_page' => $businesses->perPage(),
                        'last_page' => $businesses->lastPage(),
                        'total' => 0,
                    ],
                    'businesses' => [],
                ]);
            }

            return sendResponse(true, 'Businesses retrieved successfully.', [
                'filter' => [
                    'category_id' => $validated['category_id'] ?? null,
                    'location_id' => $validated['location_id'] ?? null,
                    'search' => $validated['search'] ?? null,
                    'featured' => $validated['featured'] ?? false,
                ],
                'count' => $businesses->total(),
                'pagination' => [
                    'current_page' => $businesses->currentPage(),
                    'per_page' => $businesses->perPage(),
                    'last_page' => $businesses->lastPage(),
                    'total' => $businesses->total(),
                ],
                'businesses' => BusinessInfoResource::collection($businesses),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get detailed information about a specific business.
     */
    #[OA\Get(
        path: '/v1/businesses/{businessId}',
        summary: 'Get detailed information about a specific business',
        description: 'Public, unauthenticated (auth optional — an authenticated caller\'s identity is used for view analytics/personalization only).',
        tags: ['Public'],
        parameters: [
            new OA\Parameter(name: 'businessId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'include_reviews', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'reviews_per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Business details retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'business', type: 'object'),
                        new OA\Property(property: 'reviews_summary', type: 'object'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 404, description: 'Business not found or not available', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function show(Request $request, $businessId)
    {
        try {
            $request->validate([
                'include_reviews' => ['nullable', 'boolean'],
                'reviews_per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
            ]);

            $detail = $this->businessInfoService->getPublicPublishedBusinessDetail((int) $businessId, $request->user('api'));

            if ($detail === null) {
                return sendResponse(false, 'Business not found or not available.', null, Response::HTTP_NOT_FOUND);
            }

            $this->boostCampaignAnalytics->recordProfileView(
                $detail['business'],
                $request->user('api'),
                $request->ip(),
            );

            return sendResponse(true, 'Business details retrieved successfully.', [
                'business' => new BusinessInfoResource($detail['business']),
                'reviews_summary' => $detail['reviews_summary'],
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get featured businesses for home page showcase.
     */
    #[OA\Get(
        path: '/v1/businesses/featured',
        summary: 'List featured businesses for the home page showcase',
        tags: ['Public'],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 20, default: 8)),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Featured businesses retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'businesses', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'count', type: 'integer'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function featured(Request $request)
    {
        try {
            $validated = $request->validate([
                'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
                'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            ]);

            $limit = $validated['limit'] ?? 8;
            $featuredBusinesses = $this->businessInfoService->getPublicFeaturedBusinesses(
                $validated,
                $request->user('api'),
                $limit
            );

            if ($featuredBusinesses->isEmpty()) {
                return sendResponse(true, 'No featured businesses found.', [
                    'businesses' => [],
                    'count' => 0,
                ]);
            }

            return sendResponse(true, 'Featured businesses retrieved successfully.', [
                'businesses' => BusinessInfoResource::collection($featuredBusinesses),
                'count' => $featuredBusinesses->count(),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all businesses for filter page (no status restrictions).
     */
    #[OA\Get(
        path: '/v1/businesses/all',
        summary: 'List all businesses for the filter page (no status restrictions)',
        tags: ['Public'],
        parameters: [
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'subcategory', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 255)),
            new OA\Parameter(name: 'location_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'lat', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float', minimum: -90, maximum: 90)),
            new OA\Parameter(name: 'lng', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float', minimum: -180, maximum: 180)),
            new OA\Parameter(name: 'radius_km', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float', minimum: 1, maximum: 200)),
            new OA\Parameter(name: 'verification_status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['none', 'pending', 'verified', 'approved'])),
            new OA\Parameter(name: 'business_status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'inactive', 'suspended'])),
            new OA\Parameter(name: 'is_flagged', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 255)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Businesses retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'filter', type: 'object'),
                        new OA\Property(property: 'count', type: 'integer'),
                        new OA\Property(property: 'pagination', type: 'object'),
                        new OA\Property(property: 'businesses', type: 'array', items: new OA\Items(type: 'object')),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function all(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_id' => ['nullable', 'integer', 'exists:categories,id'],
                'subcategory' => ['nullable', 'string', 'max:255'],
                'location_id' => ['nullable', 'integer', 'exists:locations,id'],
                'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
                'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
                'radius_km' => ['nullable', 'numeric', 'min:1', 'max:200'],
                'verification_status' => ['nullable', 'string', 'in:none,pending,verified,approved'],
                'business_status' => ['nullable', 'string', 'in:active,inactive,suspended'],
                'is_flagged' => ['nullable', 'boolean'],
                'search' => ['nullable', 'string', 'max:255'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            $perPage = $validated['per_page'] ?? 20;
            $businesses = $this->businessInfoService->paginatePublicAll(
                $validated,
                $request->user('api'),
                $perPage
            );

            if ($businesses->total() === 0) {
                return sendResponse(true, 'No businesses found.', [
                    'filter' => [
                        'category_id' => $validated['category_id'] ?? null,
                        'location_id' => $validated['location_id'] ?? null,
                        'verification_status' => $validated['verification_status'] ?? null,
                        'business_status' => $validated['business_status'] ?? null,
                        'is_flagged' => $validated['is_flagged'] ?? null,
                        'search' => $validated['search'] ?? null,
                    ],
                    'count' => 0,
                    'pagination' => [
                        'current_page' => $businesses->currentPage(),
                        'per_page' => $businesses->perPage(),
                        'last_page' => $businesses->lastPage(),
                        'total' => 0,
                    ],
                    'businesses' => [],
                ]);
            }

            return sendResponse(true, 'All businesses retrieved successfully.', [
                'filter' => [
                    'category_id' => $validated['category_id'] ?? null,
                    'location_id' => $validated['location_id'] ?? null,
                    'verification_status' => $validated['verification_status'] ?? null,
                    'business_status' => $validated['business_status'] ?? null,
                    'is_flagged' => $validated['is_flagged'] ?? null,
                    'search' => $validated['search'] ?? null,
                ],
                'count' => $businesses->total(),
                'pagination' => [
                    'current_page' => $businesses->currentPage(),
                    'per_page' => $businesses->perPage(),
                    'last_page' => $businesses->lastPage(),
                    'total' => $businesses->total(),
                ],
                'businesses' => BusinessInfoResource::collection($businesses),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Search businesses with advanced filters.
     */
    #[OA\Get(
        path: '/v1/businesses/search',
        summary: 'Search businesses with advanced filters',
        tags: ['Public'],
        parameters: [
            new OA\Parameter(name: 'query', in: 'query', required: true, schema: new OA\Schema(type: 'string', maxLength: 255)),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'location_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'lat', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float', minimum: -90, maximum: 90)),
            new OA\Parameter(name: 'lng', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float', minimum: -180, maximum: 180)),
            new OA\Parameter(name: 'radius_km', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float', minimum: 1, maximum: 200)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 50, default: 12)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Businesses found successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'search_query', type: 'string'),
                        new OA\Property(property: 'filter', type: 'object'),
                        new OA\Property(property: 'count', type: 'integer'),
                        new OA\Property(property: 'pagination', type: 'object'),
                        new OA\Property(property: 'businesses', type: 'array', items: new OA\Items(type: 'object')),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function search(Request $request)
    {
        try {
            $validated = $request->validate([
                'query' => ['required', 'string', 'max:255'],
                'category_id' => ['nullable', 'integer', 'exists:categories,id'],
                'location_id' => ['nullable', 'integer', 'exists:locations,id'],
                'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
                'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
                'radius_km' => ['nullable', 'numeric', 'min:1', 'max:200'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            $perPage = $validated['per_page'] ?? 12;
            $searchQuery = trim($validated['query']);

            $businesses = $this->businessInfoService->paginatePublicSearch(
                $validated,
                $request->user('api'),
                $perPage
            );

            if ($businesses->total() === 0) {
                return sendResponse(true, 'No businesses found matching your search.', [
                    'search_query' => $searchQuery,
                    'filter' => [
                        'category_id' => $validated['category_id'] ?? null,
                        'location_id' => $validated['location_id'] ?? null,
                    ],
                    'count' => 0,
                    'pagination' => [
                        'current_page' => $businesses->currentPage(),
                        'per_page' => $businesses->perPage(),
                        'last_page' => $businesses->lastPage(),
                        'total' => 0,
                    ],
                    'businesses' => [],
                ]);
            }

            return sendResponse(true, 'Businesses found successfully.', [
                'search_query' => $searchQuery,
                'filter' => [
                    'category_id' => $validated['category_id'] ?? null,
                    'location_id' => $validated['location_id'] ?? null,
                ],
                'count' => $businesses->total(),
                'pagination' => [
                    'current_page' => $businesses->currentPage(),
                    'per_page' => $businesses->perPage(),
                    'last_page' => $businesses->lastPage(),
                    'total' => $businesses->total(),
                ],
                'businesses' => BusinessInfoResource::collection($businesses),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
