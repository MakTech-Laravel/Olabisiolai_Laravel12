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
            ->get(['id', 'name', 'subcategories', 'created_at', 'updated_at']);

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
    public function show(Request $request)
    {
        $user = $request->user('api');
        $businessId = $request->integer('business_id');
        $business = $businessId > 0
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
            $logo = $request->file('logo');
            $coverPhotos = array_values($request->file('cover_photos', []));

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
                $logo,
                $coverPhotos,
                $subscriptionPlan,
                $validated['business_hours'] ?? null,
            );

            $business->load(['category:id,name,subcategories,created_at,updated_at', 'businessHours']);

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

    public function update(UpdateBusinessInfoRequest $request)
    {
        $user = $request->user('api');

        try {
            $validated = $request->validated();
            $logo = $request->file('logo');
            $coverPhotos = array_values($request->file('cover_photos', []));
            $keepCoverPaths = array_values($validated['keep_cover_paths'] ?? []);

            $streetAddressProvided = array_key_exists('street_address', $validated)
                || array_key_exists('full_address', $validated);

            $subcategoryProvided = array_key_exists('subcategory', $validated);

            $coordinatesProvided = array_key_exists('latitude', $validated)
                || array_key_exists('longitude', $validated)
                || array_key_exists('google_place_id', $validated);

            $businessId = $request->integer('business_id');
            $resolvedBusinessId = $businessId > 0 ? $businessId : null;

            $business = $this->businessInfoService->updateForUser(
                $user,
                (int) $validated['category_id'],
                $subcategoryProvided ? trim((string) $validated['subcategory']) : null,
                (int) $validated['location_id'],
                $validated['business_name'],
                $streetAddressProvided ? self::resolveStreetAddress($validated) : null,
                $validated['business_description'],
                $validated['services'],
                $validated['phone'],
                $validated['whatsapp'] ?? null,
                $validated['website'] ?? null,
                array_key_exists('social_accounts', $validated) ? $validated['social_accounts'] : null,
                $logo,
                $coverPhotos,
                array_key_exists('business_hours', $validated) ? $validated['business_hours'] : null,
                $streetAddressProvided,
                $subcategoryProvided,
                $request->has('keep_cover_paths') ? $keepCoverPaths : null,
                $resolvedBusinessId,
                isset($validated['latitude']) ? (float) $validated['latitude'] : null,
                isset($validated['longitude']) ? (float) $validated['longitude'] : null,
                $validated['google_place_id'] ?? null,
                $coordinatesProvided,
            );

            $business->load(['category:id,name,subcategories,created_at,updated_at', 'businessHours']);

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
