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
                'Your business page is ready. Complete your listing to start reaching customers on Gidira.',
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
        return sendResponse(
            false,
            'Customer mode switching is no longer supported.',
            null,
            Response::HTTP_FORBIDDEN,
        );
    }
}
