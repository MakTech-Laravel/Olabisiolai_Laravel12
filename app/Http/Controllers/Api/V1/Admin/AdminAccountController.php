<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AdminStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminResource;
use App\Models\Admin;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminAccountController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = isset($validated['search']) ? trim($validated['search']) : '';

        $query = Admin::query()->latest('id');

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        $admins = $query->paginate(max(1, min($perPage, 100)));

        return response()->json([
            'success' => true,
            'data' => AdminResource::collection($admins->getCollection()),
            'meta' => [
                'current_page' => $admins->currentPage(),
                'last_page' => $admins->lastPage(),
                'per_page' => $admins->perPage(),
                'total' => $admins->total(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Full admin profile for detail views and fresh RBAC state.
     */
    public function show(int $id)
    {
        $admin = Admin::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => AdminResource::make($admin),
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role = Role::query()
            ->where('guard_name', 'admin')
            ->where('name', $validated['role'])
            ->first();

        if (! $role) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role for admin guard.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $admin = Admin::query()->create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'status' => AdminStatus::Pending,
            'email_verified_at' => null,
        ]);

        $admin->assignRole($role);

        if (! empty($validated['permissions'])) {
            $admin->givePermissionTo($validated['permissions']);
        }

        try {
            $this->authService->resendOtp($admin->fresh());
        } catch (Throwable $throwable) {
            report($throwable);
        }

        return response()->json([
            'success' => true,
            'message' => 'Admin account created. Status is pending until email is verified, or until an authorized admin sets the account to active.',
            'admin' => AdminResource::make($admin->refresh()),
        ], Response::HTTP_CREATED);
    }

    public function rbacCheck(int $id, Request $request)
    {
        $validated = $request->validate([
            'role' => ['nullable', 'string'],
            'permission' => ['nullable', 'string'],
        ]);

        $admin = Admin::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'admin' => AdminResource::make($admin),
            'checks' => [
                'role' => isset($validated['role'])
                    ? ['name' => $validated['role'], 'result' => $admin->hasRole($validated['role'])]
                    : null,
                'permission' => isset($validated['permission'])
                    ? ['name' => $validated['permission'], 'result' => $admin->can($validated['permission'])]
                    : null,
            ],
        ], Response::HTTP_OK);
    }

    public function assignRolePermissions(int $id, Request $request)
    {
        /** @var Admin|null $actor */
        $actor = $request->user('admin_api') ?? $request->user('admin');

        if (! $actor || ! $actor->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Only super-admin can assign roles and permissions.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'admin')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'admin')],
        ]);

        $admin = Admin::query()->findOrFail($id);
        $admin->syncRoles([$validated['role']]);

        if (array_key_exists('permissions', $validated)) {
            $admin->syncPermissions($validated['permissions'] ?? []);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role and permissions updated successfully.',
            'admin' => AdminResource::make($admin->refresh()),
            'roles' => $admin->getRoleNames(),
            'permissions' => $admin->getAllPermissions()->pluck('name'),
        ], Response::HTTP_OK);
    }

    /**
     * Update account status (active / pending / block). Requires `change admin status` or super-admin role.
     */
    public function updateStatus(int $id, Request $request)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(AdminStatus::class)],
        ]);

        /** @var Admin $actor */
        $actor = $request->user('admin_api') ?? $request->user('admin');
        $admin = Admin::query()->findOrFail($id);

        if ($actor->id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change your own account status.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($admin->hasRole('super-admin') && ! $actor->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Only a super-admin can change another super-admin account status.',
            ], Response::HTTP_FORBIDDEN);
        }

        $rawStatus = $validated['status'];
        $next = $rawStatus instanceof AdminStatus
            ? $rawStatus
            : AdminStatus::from((string) $rawStatus);

        $payload = ['status' => $next];

        if ($next === AdminStatus::Active) {
            $payload['email_verified_at'] = now();
        } elseif ($next === AdminStatus::Pending) {
            $payload['email_verified_at'] = null;
        }

        $admin->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Admin status updated successfully.',
            'admin' => AdminResource::make($admin->refresh()),
        ], Response::HTTP_OK);
    }

    /**
     * Remove an admin account. Requires `delete admins` or super-admin role.
     */
    public function destroy(int $id, Request $request)
    {
        /** @var Admin $actor */
        $actor = $request->user('admin_api') ?? $request->user('admin');
        $admin = Admin::query()->findOrFail($id);

        if ($actor->id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($admin->hasRole('super-admin') && ! $actor->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Only a super-admin can delete another super-admin account.',
            ], Response::HTTP_FORBIDDEN);
        }

        $admin->tokens()->delete();
        $admin->syncRoles([]);
        $admin->syncPermissions([]);
        $admin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin account deleted successfully.',
        ], Response::HTTP_OK);
    }
}
