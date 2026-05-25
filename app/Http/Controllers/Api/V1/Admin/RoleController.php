<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::query()
            ->where('guard_name', 'admin')
            ->with('permissions')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'admin',
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'success' => true,
            'data' => $role->load('permissions'),
        ], Response::HTTP_CREATED);
    }

    public function show(int $id)
    {
        $role = Role::query()
            ->where('guard_name', 'admin')
            ->with('permissions')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $role,
        ], Response::HTTP_OK);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:roles,name,'.$id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role = Role::query()
            ->where('guard_name', 'admin')
            ->findOrFail($id);

        $role->update([
            'name' => $validated['name'],
        ]);

        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        return response()->json([
            'success' => true,
            'data' => $role->load('permissions'),
        ], Response::HTTP_OK);
    }

    public function destroy(int $id)
    {
        $role = Role::query()
            ->where('guard_name', 'admin')
            ->findOrFail($id);

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted',
        ], Response::HTTP_OK);
    }
}
