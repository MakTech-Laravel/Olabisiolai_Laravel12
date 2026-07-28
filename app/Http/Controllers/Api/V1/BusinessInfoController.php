<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBusinessInfoRequest;
use App\Http\Requests\Api\V1\UpdateBusinessInfoRequest;
use App\Http\Resources\Api\V1\BusinessInfoResource;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\LocationResource;
use App\Models\Category;
use App\Models\Location;
use App\Services\BusinessInfoService;
use App\Services\LocationCatalogService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BusinessInfoController extends Controller
{
    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
        private readonly LocationCatalogService $locationCatalogService,
        private readonly SubscriptionService $subscriptionService,
    ) {}

    /**
     * Categories and static locations for the business listing form.
     */
    public function formOptions()
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'subcategories', 'icon', 'created_at', 'updated_at']);

        $locations = Location::query()
            ->with('lgaBoost')
            ->orderBy('state_name')
            ->orderBy('city_name')
            ->get();

        return sendResponse(true, 'Form options retrieved successfully.', [
            'categories' => CategoryResource::collection($categories)->resolve(),
            'locations' => LocationResource::collection($locations)->resolve(),
        ]);
    }

    /**
     * Current user's business profile (if any).
     */
    #[OA\Get(
        path: '/v1/vendor/business/show/{businessId}',
        summary: 'Get vendor business profile',
        description: 'Returns the authenticated vendor\'s business profile. Pass an optional businessId to load a specific business; omit it (call `/v1/vendor/business/show`) to use the active business.',
        tags: ['Vendors'],
        security: [['passport' => []]],
        parameters: [
            new OA\Parameter(
                name: 'businessId',
                description: 'Optional business ID owned by the authenticated vendor. Leave empty to use settings.active_business_id (or the first business).',
                in: 'path',
                required: false,
                allowEmptyValue: true,
                schema: new OA\Schema(type: 'integer', example: 1, nullable: true),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Business profile retrieved successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(
                response: 404,
                description: 'No business profile found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function show(Request $request, ?int $businessId = null)
    {
        $user = $request->user('api');
        $business = $businessId !== null
            ? $this->businessInfoService->findForUser($user, $businessId)
            : $this->businessInfoService->findForUser($user);

        if ($business === null) {
            return sendResponse(false, 'No business profile found.', null, Response::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Business profile retrieved successfully.', [
            'business' => new BusinessInfoResource($business),
        ]);
    }

    public function store(StoreBusinessInfoRequest $request)
    {
        $user = $request->user('api');

        try {
            $validated = $request->validated();

            $subscriptionPlan = SubscriptionPlan::tryFrom((string) ($validated['subscription_plan'] ?? 'free'))
                ?? SubscriptionPlan::Free;

            $business = $this->businessInfoService->createForUser(
                $user,
                (int) $validated['category_id'],
                isset($validated['subcategory']) ? trim((string) $validated['subcategory']) : null,
                (int) $validated['location_id'],
                $validated['business_name'],
                self::resolveStreetAddress($validated),
                $validated['business_description'],
                $validated['services'],
                $validated['phone'],
                $validated['whatsapp'] ?? null,
                $validated['website'] ?? null,
                $validated['social_accounts'] ?? null,
                $subscriptionPlan,
                $validated['business_hours'] ?? null,
            );

            $business->load(['category:id,name,subcategories,icon,created_at,updated_at', 'businessHours']);

            $requiresPayment = $this->subscriptionService->requiresPayment($business);

            return sendResponse(
                true,
                $requiresPayment
                    ? 'Business profile created. Complete premium payment to unlock premium features.'
                    : 'Business profile created successfully.',
                [
                    'business' => new BusinessInfoResource($business),
                    'subscription' => $this->subscriptionService->subscriptionPayload($business),
                    'requires_subscription_payment' => $requiresPayment,
                ],
                Response::HTTP_CREATED,
            );
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (InvalidArgumentException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Put(
        path: '/v1/vendor/business/update',
        summary: 'Partially update vendor business profile (JSON)',
        description: 'Updates only the fields present in the request body. Omitted fields keep their current values; '
            .'send null (or empty string) on nullable fields to clear them. Prefer this endpoint for text-only edits. '
            .'Use POST /v1/vendor/business/update with multipart/form-data when uploading logo or cover photos. '
            .'Changing business_name, category_id, subcategory, or location_id may revoke verification.',
        tags: ['Vendors'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/VendorBusinessUpdatePatch',
                examples: [
                    new OA\Examples(
                        example: 'name_only',
                        summary: 'Change business name only',
                        value: ['business_id' => 1, 'business_name' => 'New Business Name'],
                    ),
                    new OA\Examples(
                        example: 'clear_website',
                        summary: 'Clear website',
                        value: ['business_id' => 1, 'website' => null],
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Business profile updated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(
                response: 404,
                description: 'No business profile found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    #[OA\Post(
        path: '/v1/vendor/business/update',
        summary: 'Partially update vendor business profile (multipart)',
        description: 'Same partial-update semantics as PUT, for requests that include file uploads. '
            .'Only sent fields are updated. Prefer PUT with JSON when not uploading files. '
            .'Send keep_cover_paths and/or cover_photos only when changing the gallery.',
        tags: ['Vendors'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/VendorBusinessUpdatePatch'),
                    ],
                    properties: [
                        new OA\Property(
                            property: 'logo',
                            description: 'Optional new logo image',
                            type: 'string',
                            format: 'binary',
                            nullable: true,
                        ),
                        new OA\Property(
                            property: 'cover_photos',
                            description: 'Optional new gallery images (combine with keep_cover_paths)',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            nullable: true,
                        ),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Business profile updated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(
                response: 404,
                description: 'No business profile found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function update(UpdateBusinessInfoRequest $request)
    {
        $user = $request->user('api');

        try {
            $validated = $request->validated();

            $businessId = $request->integer('business_id');
            $resolvedBusinessId = $businessId > 0 ? $businessId : null;

            $existingBusiness = $resolvedBusinessId !== null
                ? $this->businessInfoService->findForUser($user, $resolvedBusinessId)
                : $this->businessInfoService->findForUser($user);

            if ($existingBusiness === null) {
                return sendResponse(false, 'No business profile found.', null, Response::HTTP_NOT_FOUND);
            }

            $patch = [];

            foreach ([
                'category_id',
                'location_id',
                'subcategory',
                'business_name',
                'business_description',
                'services',
                'phone',
                'whatsapp',
                'website',
                'social_accounts',
                'business_hours',
                'latitude',
                'longitude',
                'google_place_id',
                'logo_path',
                'cover_photo_paths',
            ] as $key) {
                if (array_key_exists($key, $validated)) {
                    $patch[$key] = $validated[$key];
                }
            }

            if (array_key_exists('street_address', $validated) || array_key_exists('full_address', $validated)) {
                $patch['street_address'] = self::resolveStreetAddress($validated);
            }

            $keepCoverPaths = $request->exists('keep_cover_paths')
                ? array_values($validated['keep_cover_paths'] ?? [])
                : null;

            $business = $this->businessInfoService->updateForUser(
                $user,
                $patch,
                $keepCoverPaths,
                $resolvedBusinessId,
            );

            $business->load(['category:id,name,subcategories,icon,created_at,updated_at', 'businessHours']);

            return sendResponse(true, 'Business profile updated successfully.', [
                'business' => new BusinessInfoResource($business),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (InvalidArgumentException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_NOT_FOUND);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateBoostStatus(Request $request)
    {
        $user = $request->user('api');

        try {
            $validated = $request->validate([
                'is_active' => ['required', 'boolean'],
                'business_id' => ['sometimes', 'integer', 'min:1'],
            ]);

            $businessId = isset($validated['business_id']) ? (int) $validated['business_id'] : null;

            $boost = $this->businessInfoService->setBoostStatusForVendor($user, (bool) $validated['is_active'], $businessId);

            return sendResponse(true, 'Boost status updated successfully.', [
                'boost' => [
                    'id' => $boost->id,
                    'business_info_id' => $boost->business_info_id,
                    'is_active' => (bool) $boost->is_active,
                    'status' => $boost->is_active ? 'active' : 'none',
                    'activated_at' => humanDateTime($boost->activated_at),
                    'deactivated_at' => humanDateTime($boost->deactivated_at),
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_NOT_FOUND);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private static function resolveStreetAddress(array $validated): ?string
    {
        $raw = trim((string) ($validated['street_address'] ?? $validated['full_address'] ?? ''));

        return $raw !== '' ? $raw : null;
    }
}
