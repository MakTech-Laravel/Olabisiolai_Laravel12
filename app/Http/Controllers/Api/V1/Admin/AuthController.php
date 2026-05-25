<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminResource;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::query()->where('email', $validated['email'])->first();

        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $admin->createToken('admin-token')->accessToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'admin' => AdminResource::make($admin),
            'roles' => $admin->getRoleNames(),
            'permissions' => $admin->getAllPermissions()->pluck('name'),
        ], Response::HTTP_OK);
    }

    public function logout(Request $request)
    {
        ($request->user('admin_api') ?? $request->user('admin'))?->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out',
        ], Response::HTTP_OK);
    }

    public function me(Request $request)
    {
        /** @var Admin $admin */
        $admin = $request->user('admin_api') ?? $request->user('admin');

        return response()->json([
            'admin' => AdminResource::make($admin),
            'roles' => $admin->getRoleNames(),
            'permissions' => $admin->getAllPermissions()->pluck('name'),
        ], Response::HTTP_OK);
    }
}
