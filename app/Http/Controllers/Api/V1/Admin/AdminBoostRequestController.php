<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BoostPurchaseRequestDetailResource;
use App\Http\Resources\Api\V1\BoostPurchaseRequestResource;
use App\Models\Admin;
use App\Models\BoostPurchaseRequest;
use App\Services\BoostPurchaseService;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminBoostRequestController extends Controller
{
    public function __construct(
        private readonly BoostPurchaseService $boostPurchaseService,
    ) {}

    private function resolveAdmin(Request $request): Admin
    {
        $admin = adminAuthCheck($request);

        if (! $admin instanceof Admin) {
            throw new RuntimeException('Admin authentication required.');
        }

        return $admin;
    }

    public function waitingList(Request $request)
    {
        try {
            $rows = $this->boostPurchaseService->waitingListForAdmin();

            return sendResponse(true, 'Boost waiting list retrieved successfully.', [
                'requests' => BoostPurchaseRequestResource::collection($rows)->resolve(),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:boost_purchase_requests,id'],
            ]);

            $boostRequest = $this->boostPurchaseService->findForAdminDetail((int) $validated['id']);

            return sendResponse(true, 'Boost request details retrieved successfully.', [
                'request' => new BoostPurchaseRequestDetailResource($boostRequest),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function flag(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:boost_purchase_requests,id'],
                'is_flagged' => ['required', 'boolean'],
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            $boostRequest = BoostPurchaseRequest::query()->findOrFail((int) $validated['id']);
            $updated = $this->boostPurchaseService->setFlagged(
                $boostRequest,
                (bool) $validated['is_flagged'],
                $validated['note'] ?? null,
            );

            return sendResponse(true, $validated['is_flagged'] ? 'Boost request flagged.' : 'Flag removed from boost request.', [
                'request' => new BoostPurchaseRequestResource($updated),
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function campaigns(Request $request)
    {
        try {
            $validated = $request->validate([
                'display_status' => ['nullable', 'string', 'max:32'],
            ]);

            $rows = $this->boostPurchaseService->listCampaignsForAdmin($validated['display_status'] ?? null);

            return sendResponse(true, 'Boost campaigns retrieved successfully.', [
                'campaigns' => BoostPurchaseRequestResource::collection($rows)->resolve(),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'status' => ['nullable', 'string', 'max:32'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $paginator = $this->boostPurchaseService->listForAdmin(
                $validated['status'] ?? null,
                (int) ($validated['per_page'] ?? 15),
            );

            return sendResponse(true, 'Boost requests retrieved successfully.', [
                'requests' => BoostPurchaseRequestResource::collection($paginator->items())->resolve(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function approve(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:boost_purchase_requests,id'],
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            $boostRequest = BoostPurchaseRequest::query()->findOrFail((int) $validated['id']);
            $approved = $this->boostPurchaseService->approve(
                $boostRequest,
                $this->resolveAdmin($request),
                $validated['note'] ?? null,
            );

            $endsLabel = $approved->ends_at?->format('M j, Y g:i A') ?? '';

            return sendResponse(true, "Boost is live now and runs until {$endsLabel}.", [
                'request' => new BoostPurchaseRequestResource($approved),
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function reject(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:boost_purchase_requests,id'],
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            $boostRequest = BoostPurchaseRequest::query()->findOrFail((int) $validated['id']);
            $rejected = $this->boostPurchaseService->reject(
                $boostRequest,
                $this->resolveAdmin($request),
                $validated['note'] ?? null,
            );

            return sendResponse(true, 'Boost request rejected.', [
                'request' => new BoostPurchaseRequestResource($rejected),
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
