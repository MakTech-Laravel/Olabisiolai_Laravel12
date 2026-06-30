<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ConfirmTwoFactorRequest;
use App\Http\Requests\Api\V1\DisableTwoFactorRequest;
use App\Models\Admin;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminTwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function status(Request $request): Response
    {
        /** @var Admin $admin */
        $admin = $request->user('admin_api') ?? $request->user('admin');

        return sendResponse(true, 'Two-factor status retrieved.', [
            'enabled' => $this->twoFactor->isEnabled($admin),
            'confirmed' => $admin->two_factor_confirmed_at !== null,
        ]);
    }

    public function enable(Request $request): Response
    {
        /** @var Admin $admin */
        $admin = $request->user('admin_api') ?? $request->user('admin');

        if ($this->twoFactor->isEnabled($admin)) {
            return sendResponse(false, 'Two-factor authentication is already enabled.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $payload = $this->twoFactor->enable($admin);

            return sendResponse(true, 'Scan the QR code with your authenticator app, then confirm with a 6-digit code.', [
                'secret' => $payload['secret'],
                'qr_code' => $payload['qr_code'],
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function confirm(ConfirmTwoFactorRequest $request): Response
    {
        /** @var Admin $admin */
        $admin = $request->user('admin_api') ?? $request->user('admin');

        if ($this->twoFactor->isEnabled($admin)) {
            return sendResponse(false, 'Two-factor authentication is already enabled.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->twoFactor->confirm($admin, (string) $request->validated('code'));

            return sendResponse(true, 'Two-factor authentication enabled. Store your recovery codes in a safe place.', [
                'enabled' => true,
                'recovery_codes' => $result['recovery_codes'],
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function disable(DisableTwoFactorRequest $request): Response
    {
        /** @var Admin $admin */
        $admin = $request->user('admin_api') ?? $request->user('admin');

        if (! $this->twoFactor->isEnabled($admin)) {
            return sendResponse(false, 'Two-factor authentication is not enabled.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            if (! Hash::check((string) $request->validated('password'), $admin->password)) {
                throw ValidationException::withMessages([
                    'password' => ['The password is incorrect.'],
                ]);
            }

            $this->twoFactor->disable($admin);

            return sendResponse(true, 'Two-factor authentication has been disabled.', [
                'enabled' => false,
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
