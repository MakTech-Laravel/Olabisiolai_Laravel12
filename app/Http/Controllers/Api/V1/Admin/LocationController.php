<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ChangeLocationStatusRequest;
use App\Http\Requests\Api\V1\Admin\StoreLocationRequest;
use App\Http\Requests\Api\V1\Admin\SyncLgaVendorsRequest;
use App\Http\Requests\Api\V1\Admin\ToggleBoostActiveRequest;
use App\Http\Requests\Api\V1\Admin\UpdateLocationRequest;
use App\Http\Resources\Api\V1\LocationListResource;
use App\Http\Resources\Api\V1\LocationResource;
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LocationController extends Controller
{
    private LocationService $locationService;

    public function __construct(LocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    public function index(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'search' => ['nullable', 'string', 'max:255'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
                'filter_boost' => ['nullable', 'string', 'in:enabled,disabled'],
                'all' => ['nullable', 'boolean'],
            ]);

            $search = isset($validated['search']) ? (string) $validated['search'] : null;
            $filterBoost = $validated['filter_boost'] ?? null;

            if ($request->boolean('all')) {
                $locations = $this->locationService->listAllLocations($search, $filterBoost);

                return sendResponse(true, 'Locations retrieved successfully.', [
                    'filter' => [
                        'search' => $search !== null && $search !== '' ? trim($search) : null,
                        'boost' => $filterBoost,
                    ],
                    'count' => $locations->count(),
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => $locations->count(),
                        'last_page' => 1,
                        'total' => $locations->count(),
                    ],
                    'locations' => LocationListResource::collection($locations),
                ]);
            }

            $locations = $this->locationService->listLocations(
                $search,
                $validated['per_page'] ?? 15,
                $filterBoost,
            );

            if ($locations->total() === 0) {
                return sendResponse(true, 'No locations found.', [
                    'filter' => [
                        'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
                        'boost' => $validated['filter_boost'] ?? null,
                    ],
                    'count' => 0,
                    'pagination' => [
                        'current_page' => $locations->currentPage(),
                        'per_page' => $locations->perPage(),
                        'last_page' => $locations->lastPage(),
                        'total' => 0,
                    ],
                    'locations' => [],
                ]);
            }

            return sendResponse(true, 'Locations retrieved successfully.', [
                'filter' => [
                    'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
                    'boost' => $validated['filter_boost'] ?? null,
                ],
                'count' => $locations->total(),
                'pagination' => [
                    'current_page' => $locations->currentPage(),
                    'per_page' => $locations->perPage(),
                    'last_page' => $locations->lastPage(),
                    'total' => $locations->total(),
                ],
                'locations' => LocationListResource::collection($locations),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreLocationRequest $request)
    {

        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }
            $payload = $this->locationService->storeLocation($request->validated());

            return sendResponse(true, 'Location saved successfully.', new LocationResource($payload), Response::HTTP_CREATED);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateLocationRequest $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validated();
            $locationId = $validated['id'] ?? null;

            if (! $locationId) {
                return sendResponse(false, 'Location ID is required.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $payload = $this->locationService->updateLocation($locationId, $validated);

            return sendResponse(true, 'Location updated successfully.', new LocationResource($payload));
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:locations,id'],
            ]);

            $this->locationService->deleteLocation($validated['id']);

            return sendResponse(true, 'Location deleted successfully.');
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function toggleBoostActive(ToggleBoostActiveRequest $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validated();
            $locationId = $validated['id'] ?? null;

            if (! $locationId) {
                return sendResponse(false, 'Location ID is required.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $location = $this->locationService->toggleBoostActive($locationId, $validated['boost_active']);

            return sendResponse(true, 'Boost status updated successfully.', [
                'location_id' => $location->id,
                'boost_active' => $location->lgaBoost?->enabled ?? false,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function locationVendors(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:locations,id'],
            ]);

            $vendors = $this->locationService->locationVendors($validated['id']);

            return sendResponse(true, 'Location vendors retrieved successfully.', [
                'location_id' => $validated['id'],
                'vendors' => $vendors,
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function syncLocationVendors(SyncLgaVendorsRequest $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validated();
            $locationId = $validated['id'] ?? null;

            if (! $locationId) {
                return sendResponse(false, 'Location ID is required.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->locationService->syncLocationVendors($locationId, $validated['vendors']);

            return sendResponse(true, 'Location vendors synced successfully.');
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function changeStatus(ChangeLocationStatusRequest $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validated();
            $locationId = $validated['id'] ?? null;

            if (! $locationId) {
                return sendResponse(false, 'Location ID is required.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $location = $this->locationService->changeLocationStatus($locationId, $validated['country_is_active']);

            return sendResponse(true, 'Location status changed successfully.', new LocationResource($location));
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
