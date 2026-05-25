<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FavoriteResource;
use App\Models\BusinessInfo;
use App\Models\Favorite;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserFavoritesController extends Controller
{
    private function canManageFavorites(?User $user): bool
    {
        return $user !== null && ($user->isUser() || $user->isVendor());
    }

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $this->canManageFavorites($user)) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $validated = $request->validate([
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            $perPage = $validated['per_page'] ?? 12;
            $page = $validated['page'] ?? 1;

            $favoritesQuery = Favorite::query()
                ->where('user_id', $user->id)
                ->whereHas('business', function ($query) {
                    $this->scopeFavoritableBusiness($query);
                })
                ->with([
                    'business' => function ($query) {
                        $query->with([
                            'category:id,name,subcategories',
                            'location:id,lga_name,state_name,city_name,country_name',
                            'user:id,uuid',
                        ]);
                    },
                ])
                ->latest('created_at');

            $favorites = $favoritesQuery->paginate(
                $perPage,
                ['*'],
                'page',
                $page
            );

            $businessIds = $favorites->getCollection()->pluck('business_info_id')->values()->all();

            $statsByBusinessId = $businessIds !== []
                ? Review::query()
                ->approved()
                ->whereIn('business_id', $businessIds, 'and', false)
                ->selectRaw('business_id, AVG(rating) as avg_rating, COUNT(*) as reviews_count')
                ->groupBy('business_id')
                ->get()
                ->keyBy('business_id')
                : collect();

            foreach ($favorites->getCollection() as $favorite) {
                $business = $favorite->business;
                if ($business === null) {
                    continue;
                }

                $stats = $statsByBusinessId->get($business->id);

                $business->setAttribute('avg_rating', $stats?->avg_rating ?? 0);
                $business->setAttribute('reviews_count', $stats?->reviews_count ?? 0);
            }

            return sendResponse(true, 'Favorites retrieved successfully.', [
                'favorites' => FavoriteResource::collection($favorites->getCollection())->toArray($request),
                'count' => $favorites->total(),
                'pagination' => [
                    'current_page' => $favorites->currentPage(),
                    'per_page' => $favorites->perPage(),
                    'last_page' => $favorites->lastPage(),
                    'total' => $favorites->total(),
                ],
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

    public function toggle(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $this->canManageFavorites($user)) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', Rule::exists('business_info', 'id')],
            ]);

            $businessInfoId = (int) $validated['business_info_id'];

            $business = BusinessInfo::query()
                ->whereKey($businessInfoId)
                ->tap(fn($query) => $this->scopeFavoritableBusiness($query))
                ->first();

            if ($business === null) {
                return sendResponse(false, 'Business not found or not eligible for favorites.', null, Response::HTTP_NOT_FOUND);
            }

            /** @var Favorite|null $favorite */
            $favorite = Favorite::query()
                ->where('user_id', $user->id)
                ->where('business_info_id', $businessInfoId)
                ->first();

            if ($favorite instanceof Favorite) {
                $favorite->forceDelete();

                return sendResponse(true, 'Removed from favorites.', [
                    'favorited' => false,
                    'business_info_id' => $businessInfoId,
                ]);
            }

            Favorite::query()->create([
                'user_id' => $user->id,
                'business_info_id' => $businessInfoId,
            ]);

            return sendResponse(true, 'Added to favorites.', [
                'favorited' => true,
                'business_info_id' => $businessInfoId,
            ], Response::HTTP_CREATED);
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

    public function destroy(Request $request, int $businessId): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $this->canManageFavorites($user)) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            if (! BusinessInfo::query()->whereKey($businessId)->exists()) {
                return sendResponse(false, 'Business not found.', null, Response::HTTP_NOT_FOUND);
            }

            $deleted = Favorite::query()
                ->where('user_id', $user->id)
                ->where('business_info_id', $businessId)
                ->delete();

            if ($deleted === 0) {
                return sendResponse(false, 'Favorite not found.', null, Response::HTTP_NOT_FOUND);
            }

            return sendResponse(true, 'Removed from favorites.', null, Response::HTTP_OK);
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

    public function exists(Request $request, int $businessId): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $this->canManageFavorites($user)) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $exists = Favorite::query()
                ->where('user_id', $user->id)
                ->where('business_info_id', $businessId)
                ->exists();

            return sendResponse(true, 'Favorite status retrieved successfully.', [
                'favorited' => $exists,
                'business_info_id' => $businessId,
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
     * Match public marketplace visibility: active, not flagged (same as listing detail).
     */
    private function scopeFavoritableBusiness($query): void
    {
        $query
            ->where('business_status', BusinessStatus::Active->value)
            ->where('is_flagged', false);
    }
}
