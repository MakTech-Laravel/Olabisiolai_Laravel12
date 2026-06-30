<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAdminProfileRequest;
use App\Http\Resources\Api\V1\AdminResource;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminProfileController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var Admin $admin */
        $admin = $request->user('admin_api') ?? $request->user('admin');

        return sendResponse(true, 'Admin profile retrieved.', [
            'admin' => AdminResource::make($admin),
            'roles' => $admin->getRoleNames(),
            'permissions' => $admin->getAllPermissions()->pluck('name'),
        ]);
    }

    public function update(UpdateAdminProfileRequest $request): Response
    {
        /** @var Admin $admin */
        $admin = $request->user('admin_api') ?? $request->user('admin');

        try {
            $validated = $request->validated();

            if (array_key_exists('first_name', $validated)) {
                $admin->first_name = $validated['first_name'];
            }
            if (array_key_exists('last_name', $validated)) {
                $admin->last_name = $validated['last_name'];
            }
            if (array_key_exists('first_name', $validated) || array_key_exists('last_name', $validated)) {
                $admin->name = trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? ''));
            }
            if (array_key_exists('phone', $validated)) {
                $admin->phone = $validated['phone'];
            }
            if (array_key_exists('email', $validated)) {
                $admin->email = Str::lower($validated['email']);
            }

            if (! empty($validated['password'])) {
                if (empty($validated['current_password']) || ! Hash::check($validated['current_password'], $admin->password)) {
                    throw ValidationException::withMessages([
                        'current_password' => ['The current password is incorrect.'],
                    ]);
                }

                $admin->password = $validated['password'];
            }

            $admin->save();
            $admin->refresh();

            return sendResponse(true, 'Profile updated successfully.', [
                'admin' => AdminResource::make($admin),
                'roles' => $admin->getRoleNames(),
                'permissions' => $admin->getAllPermissions()->pluck('name'),
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
}
