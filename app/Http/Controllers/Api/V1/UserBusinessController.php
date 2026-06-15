<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BusinessInfoResource;
use App\Services\BusinessInfoService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserBusinessController extends Controller
{
    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
    ) {}

    public function index(Request $request): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user('api');

        if (! $user) {
            return sendResponse(false, 'Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        $businesses = $user->businessInfos()
            ->with(['category', 'location', 'subscription'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return sendResponse(true, 'Businesses retrieved successfully.', [
            'businesses' => BusinessInfoResource::collection($businesses),
        ]);
    }

    public function store(Request $request): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user('api');

        if (! $user) {
            return sendResponse(false, 'Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $business = $this->businessInfoService->createAdditionalBusinessForUser(
                $user,
                $validated['business_name'] ?? null,
            );

            return sendResponse(true, 'Business page created successfully.', [
                'business' => new BusinessInfoResource($business),
                'created' => true,
            ], Response::HTTP_CREATED);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
