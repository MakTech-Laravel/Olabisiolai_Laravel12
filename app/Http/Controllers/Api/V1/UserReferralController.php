<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReferralService;
use Symfony\Component\HttpFoundation\Response;

class UserReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {}

    public function show(): Response
    {
        /** @var User|null $user */
        $user = request()->user('api');
        if ($user === null) {
            return sendResponse(false, 'Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        return sendResponse(true, 'Referrals retrieved successfully.', [
            'referrals' => $this->referralService->referralsPayload($user),
        ]);
    }
}
