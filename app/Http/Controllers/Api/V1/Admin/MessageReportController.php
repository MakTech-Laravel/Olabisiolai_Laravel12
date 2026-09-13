<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MessageReportReason;
use App\Enums\ReviewReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MessageReportResource;
use App\Models\MessageReport;
use App\Services\MessageReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MessageReportController extends Controller
{
    public function __construct(
        private readonly MessageReportService $messageReportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(ReviewReportStatus::values())],
            'reason' => ['nullable', 'string', Rule::in(MessageReportReason::values())],
            'reported_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $reports = $this->messageReportService->getReports($validated);

        return response()->json([
            'success' => true,
            'data' => MessageReportResource::collection($reports->items()),
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    public function show(MessageReport $messageReport): JsonResponse
    {
        $messageReport->load([
            'message:id,uuid,conversation_id,sender_id,body,type,created_at',
            'conversation:id,uuid',
            'reporter:id,first_name,last_name,email,role,status',
            'reportedUser:id,first_name,last_name,email,role,status',
        ]);

        return response()->json([
            'success' => true,
            'data' => new MessageReportResource($messageReport),
        ]);
    }

    public function dismiss(Request $request, MessageReport $messageReport): JsonResponse
    {
        $admin = adminAuthCheck($request);
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = $this->messageReportService->dismissReport(
            $messageReport,
            $admin,
            $validated['admin_note'] ?? null,
        );

        return sendResponse(true, 'Message report dismissed successfully.', [
            'report' => new MessageReportResource($report),
        ]);
    }

    public function resolve(Request $request, MessageReport $messageReport): JsonResponse
    {
        $admin = adminAuthCheck($request);
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = $this->messageReportService->resolveReport(
            $messageReport,
            $admin,
            $validated['admin_note'] ?? null,
        );

        return sendResponse(true, 'Message report resolved successfully.', [
            'report' => new MessageReportResource($report),
        ]);
    }

    public function emailReportedUser(Request $request, MessageReport $messageReport): JsonResponse
    {
        $admin = adminAuthCheck($request);
        if (! $admin) {
            return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $report = $this->messageReportService->emailReportedUser(
                $messageReport,
                $admin,
                $validated['subject'],
                $validated['body'],
            );

            return sendResponse(true, 'Email sent to reported user.', [
                'report' => new MessageReportResource($report),
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function suspendReportedUser(Request $request, MessageReport $messageReport): JsonResponse
    {
        $admin = adminAuthCheck($request);
        if (! $admin) {
            return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $report = $this->messageReportService->suspendReportedUser(
                $messageReport,
                $admin,
                $validated['admin_note'] ?? null,
            );

            return sendResponse(true, 'Reported user suspended successfully.', [
                'report' => new MessageReportResource($report),
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function statistics(): JsonResponse
    {
        return sendResponse(true, 'Message report statistics retrieved successfully.', [
            'pending' => $this->messageReportService->pendingCount(),
            'total' => MessageReport::query()->count(),
        ]);
    }
}
