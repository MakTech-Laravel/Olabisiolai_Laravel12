<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReviewReportReason;
use App\Enums\ReviewReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BusinessReportResource;
use App\Models\BusinessReport;
use App\Services\BusinessReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessReportController extends Controller
{
    public function __construct(
        private readonly BusinessReportService $businessReportService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:' . implode(',', ReviewReportStatus::values())],
            'reason' => ['nullable', 'string', 'in:' . implode(',', ReviewReportReason::values())],
            'business_info_id' => ['nullable', 'integer', 'exists:business_info,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $reports = $this->businessReportService->getReports($validated);

        return response()->json([
            'success' => true,
            'data' => BusinessReportResource::collection($reports->items()),
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    public function show(BusinessReport $businessReport): JsonResponse
    {
        $businessReport->load([
            'business:id,business_name',
            'user:id,first_name,last_name,email',
        ]);

        return response()->json([
            'success' => true,
            'data' => new BusinessReportResource($businessReport),
        ]);
    }

    public function dismiss(BusinessReport $businessReport): JsonResponse
    {
        $report = $this->businessReportService->dismissReport($businessReport);

        return response()->json([
            'success' => true,
            'data' => new BusinessReportResource($report),
            'message' => 'Business report dismissed successfully.',
        ]);
    }

    public function resolve(BusinessReport $businessReport): JsonResponse
    {
        $report = $this->businessReportService->resolveReport($businessReport);

        return response()->json([
            'success' => true,
            'data' => new BusinessReportResource($report),
            'message' => 'Business report resolved successfully.',
        ]);
    }

    public function statistics(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'pending' => $this->businessReportService->pendingCount(),
                'total' => BusinessReport::count(),
            ],
        ]);
    }
}
