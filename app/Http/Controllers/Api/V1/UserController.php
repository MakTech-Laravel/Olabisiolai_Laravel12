<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserDetailResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\ReferralService;
use App\Services\UserService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
        private WalletService $walletService,
        private ReferralService $referralService,
    ) {}

    public function userManagementSummary(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $stats = $this->userService->getUserManagementSummaryStatistics();

            return sendResponse(true, 'User management summary retrieved successfully.', [
                'summary' => $stats,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function allUsers(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'role' => ['nullable', Rule::in(['user', 'vendor', 'admin'])],
                'status' => ['nullable', Rule::in(UserStatus::values())],
                'search' => ['nullable', 'string', 'max:255'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            $users = $this->userService->paginateUsers($validated, $validated['per_page'] ?? 10);

            return sendResponse(true, 'Users retrieved successfully.', [
                'filter' => [
                    'role' => $validated['role'] ?? 'all',
                    'status' => $validated['status'] ?? 'all',
                    'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
                ],
                'count' => $users->total(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'last_page' => $users->lastPage(),
                    'total' => $users->total(),
                ],
                'users' => UserResource::collection($users),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function viewUser(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
            ]);

            $user = $this->userService->getUserByIdForAdmin((int) $validated['user_id']);

            return sendResponse(true, 'User retrieved successfully.', [
                'user' => new UserDetailResource($user),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function changeUserStatus(Request $request)
    {
        try {
            $admin = adminAuthCheck($request);

            if (! $admin) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'status' => ['required', Rule::in(UserStatus::values())],
            ], [
                'user_id.required' => 'The user id field is required.',
                'status.required' => 'The status field is required.',
            ]);

            $user = $this->userService->getUserById((int) $validated['user_id']);

            if ($admin->id === $user->id) {
                return sendResponse(false, 'You cannot change your own status.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user = $this->userService->changeStatus($user, (string) $validated['status']);

            if ($validated['status'] === UserStatus::Block->value) {
                // Force logout from all devices after account is blocked.
                $this->userService->revokeAllTokens($user);
            }

            return sendResponse(true, 'User status updated successfully.', [
                'user' => new UserResource($user),
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

    public function deleteUser(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
            ], [
                'user_id.required' => 'The user id field is required.',
                'user_id.integer' => 'The user id must be an integer.',
                'user_id.exists' => 'The user id does not exist.',
            ]);

            $user = $this->userService->getUserById((int) $validated['user_id']);

            if ($request->user()?->id === $user->id) {
                return sendResponse(false, 'You cannot delete your own account.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->userService->deleteUser($user);

            return sendResponse(true, 'User deleted successfully.');
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

    public function userWallet(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
                'type' => ['nullable', 'string', 'in:credit,debit'],
            ], [
                'user_id.required' => 'The user id field is required.',
                'user_id.integer' => 'The user id must be an integer.',
                'user_id.exists' => 'The user id does not exist.',
            ]);

            $user = $this->userService->getUserById((int) $validated['user_id']);
            $wallet = $this->walletService->adminWalletPayload(
                $user,
                (int) ($validated['per_page'] ?? 50),
                (int) ($validated['page'] ?? 1),
                $validated['type'] ?? null,
            );
            $wallet['referrals'] = $this->referralService->referralsPayload($user);

            return sendResponse(true, 'User wallet retrieved successfully.', [
                'wallet' => $wallet,
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
