<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BusinessInfoResource;
use App\Services\BoostCampaignAnalyticsService;
use App\Services\BusinessInfoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
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

    /**
     * Get businesses for home page display.
     * Returns active businesses (verified badge shown only when verification is approved).
     */
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
