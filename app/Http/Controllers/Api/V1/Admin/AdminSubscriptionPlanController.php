<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSubscriptionPlanRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSubscriptionPlanRequest;
use App\Http\Resources\Api\V1\PricingPackageResource;
use App\Services\PricingPackageService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminSubscriptionPlanController extends Controller
{
    public function __construct(
        private readonly PricingPackageService $pricingPackageService,
    ) {}

    public function index(Request $request)
    {
        if (! adminAuthCheck($request)) {
            return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
        }

        return sendResponse(true, 'Subscription plans retrieved successfully.', [
            'plans' => PricingPackageResource::collection($this->pricingPackageService->subscriptionPlansForAdmin()),
        ]);
    }

    public function store(StoreSubscriptionPlanRequest $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $plan = $this->pricingPackageService->createPlan($request->validated());

            return sendResponse(true, 'Subscription plan created successfully.', new PricingPackageResource($plan), Response::HTTP_CREATED);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateSubscriptionPlanRequest $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validated();
            $plan = $this->pricingPackageService->updatePlan((int) $validated['id'], $validated);

            return sendResponse(true, 'Subscription plan updated successfully.', new PricingPackageResource($plan));
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:pricing_packages,id'],
            ]);

            $this->pricingPackageService->deletePlan((int) $validated['id']);

            return sendResponse(true, 'Subscription plan deleted successfully.');
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function toggleActive(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:pricing_packages,id'],
                'is_active' => ['required', 'boolean'],
            ]);

            $plan = $this->pricingPackageService->setActive((int) $validated['id'], (bool) $validated['is_active']);

            return sendResponse(true, 'Subscription plan status updated successfully.', new PricingPackageResource($plan));
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function setRecommended(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:pricing_packages,id'],
            ]);

            $plan = $this->pricingPackageService->setRecommended((int) $validated['id']);

            return sendResponse(true, 'Recommended plan updated successfully.', new PricingPackageResource($plan));
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function reorder(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'ordered_ids' => ['required', 'array', 'min:1'],
                'ordered_ids.*' => ['integer', 'exists:pricing_packages,id'],
            ]);

            $this->pricingPackageService->reorder($validated['ordered_ids']);

            return sendResponse(true, 'Subscription plans reordered successfully.', [
                'plans' => PricingPackageResource::collection($this->pricingPackageService->subscriptionPlansForAdmin()),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
