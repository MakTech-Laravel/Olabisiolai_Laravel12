<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserFollowVendorResource;
use App\Models\User;
use App\Models\UserFollow;
use App\Services\UserFollowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserFollowController extends Controller
{
    public function __construct(
        private readonly UserFollowService $userFollowService,
    ) {}

    public function stats(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $this->userFollowService->canManageFollows($user)) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $validated = $request->validate([
                'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            ]);

            $targetId = isset($validated['user_id'])
                ? (int) $validated['user_id']
                : $user->id;

            return sendResponse(true, 'Follow stats retrieved successfully.', [
                'user_id' => $targetId,
                'followers_count' => $this->userFollowService->followersCount($targetId),
                'following_count' => $this->userFollowService->followingCount($targetId),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function following(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if (! $this->userFollowService->canManageFollows($user)) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $validated = $request->validate([
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            $perPage = $validated['per_page'] ?? 12;
            $page = $validated['page'] ?? 1;

            $follows = UserFollow::query()
                ->where('follower_id', $user->id)
                ->with([
                    'following.businessInfo' => function ($query): void {
                        $query->with([
                            'category:id,name,subcategories',
                            'location:id,lga_name,state_name,city_name,country_name',
                        ]);
                    },
                ])
                ->latest('created_at')
                ->paginate($perPage, ['*'], 'page', $page);

            return sendResponse(true, 'Following list retrieved successfully.', [
                'following' => UserFollowVendorResource::collection($follows->getCollection())->toArray($request),
                'count' => $follows->total(),
                'pagination' => [
                    'current_page' => $follows->currentPage(),
                    'per_page' => $follows->perPage(),
                    'last_page' => $follows->lastPage(),
                    'total' => $follows->total(),
                ],
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
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

        if (! $this->userFollowService->canManageFollows($user)) {
            return sendResponse(false, 'Access denied.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $validated = $request->validate([
                'following_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            ]);

            $target = User::query()->find((int) $validated['following_user_id']);

            if (! $target instanceof User) {
                return sendResponse(false, 'User not found.', null, Response::HTTP_NOT_FOUND);
            }

            $result = $this->userFollowService->toggle($user, $target);

            return sendResponse(
                true,
                'Follow status updated.',
                [
                    ...$result,
                    'followers_count' => $this->userFollowService->followersCount($target->id),
                ],
                $result['following'] ? Response::HTTP_CREATED : Response::HTTP_OK,
            );
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
