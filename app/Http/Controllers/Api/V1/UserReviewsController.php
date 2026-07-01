<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserReviewsController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService
    ) {}

    /**
     * List reviews submitted by the authenticated user (non-anonymous only).
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        if ($user === null) {
            return sendResponse(false, 'Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        try {
            $validated = $request->validate([
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
                'page' => ['nullable', 'integer', 'min:1'],
                'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            ]);

            $reviews = $this->reviewService->getUserReviews($user->id, $validated);

            return sendResponse(true, 'Your reviews retrieved successfully.', [
                'reviews' => ReviewResource::collection($reviews->getCollection())->toArray($request),
                'count' => $reviews->total(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'per_page' => $reviews->perPage(),
                    'last_page' => $reviews->lastPage(),
                    'total' => $reviews->total(),
                ],
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
