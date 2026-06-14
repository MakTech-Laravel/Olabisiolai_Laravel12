<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserModeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserModeController extends Controller
{
    public function __construct(
        private readonly UserModeService $userModeService,
    ) {}

    public function switchToVendor(Request $request)
    {
        /** @var User $user */
        $user = $request->user('api');

        try {
            return sendResponse(
                true,
                'Vendor mode enabled. Complete your business profile to start listing your business on Gidira.',
                $this->userModeService->switchToVendorPayload($user),
            );
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

    public function switchToCustomer(Request $request)
    {
        /** @var User $user */
        $user = $request->user('api');

        try {
            return sendResponse(
                true,
                'Customer mode enabled. Your business profile and followers are unchanged.',
                $this->userModeService->switchToCustomerPayload($user),
            );
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
