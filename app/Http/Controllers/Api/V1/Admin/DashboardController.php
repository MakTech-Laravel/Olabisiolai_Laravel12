<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $adminDashboardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'range' => ['sometimes', 'string', 'in:weekly,monthly'],
            ]);

            $payload = $this->adminDashboardService->getDashboard($validated['range'] ?? 'monthly');

            return sendResponse(true, 'Admin dashboard retrieved successfully.', $payload);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function sidebarCounts(): JsonResponse
    {
        try {
            return sendResponse(true, 'Sidebar counts retrieved successfully.', $this->adminDashboardService->getSidebarCounts());
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
