<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AdminPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminPaymentsController extends Controller
{
    private const EXPORT_ROW_LIMIT = 10_000;

    public function __construct(
        private readonly AdminPaymentService $adminPaymentService,
    ) {}

    #[OA\Get(
        path: '/v1/admin/payments',
        summary: 'List all platform payments (admin)',
        tags: ['Admin', 'Billing'],
        security: [['passport' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'pending', 'completed', 'failed'])),
            new OA\Parameter(name: 'purpose', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'subscription', 'boost', 'boosting', 'verification'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 120)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payments retrieved successfully', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Not an admin, or missing permission', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => ['sometimes', 'integer', 'min:1'],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'status' => ['sometimes', 'string', 'in:all,'.implode(',', PaymentStatus::values())],
                'purpose' => ['sometimes', 'string', 'in:all,subscription,boost,boosting,verification,wallet_top_up,wallet_topup'],
                'search' => ['sometimes', 'string', 'max:120'],
            ]);

            $result = $this->adminPaymentService->paginate($validated);

            return sendResponse(true, 'Payments retrieved successfully.', $result);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Get(
        path: '/v1/admin/payments/analytics',
        summary: 'Get platform payment analytics (admin)',
        tags: ['Admin', 'Billing'],
        security: [['passport' => []]],
        parameters: [
            new OA\Parameter(name: 'trend_range', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['monthly', 'yearly'], default: 'monthly')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Analytics retrieved successfully', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Not an admin, or missing permission', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function analytics(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'trend_range' => ['sometimes', 'string', 'in:monthly,yearly'],
            ]);

            $analytics = $this->adminPaymentService->analytics($validated['trend_range'] ?? 'monthly');

            return sendResponse(true, 'Payment analytics retrieved successfully.', $analytics);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Get(
        path: '/v1/admin/payments/{payment}',
        summary: 'Get a single payment (admin)',
        tags: ['Admin', 'Billing'],
        security: [['passport' => []]],
        parameters: [
            new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'payment', ref: '#/components/schemas/Payment'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Not an admin, or missing permission', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Payment not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function show(int $payment): JsonResponse
    {
        try {
            $model = Payment::query()
                ->with(['user', 'businessInfo'])
                ->whereKey($payment)
                ->first();

            if ($model === null) {
                return sendResponse(false, 'Payment not found.', null, Response::HTTP_NOT_FOUND);
            }

            return sendResponse(true, 'Payment retrieved successfully.', [
                'payment' => $this->adminPaymentService->toAdminDetail($model),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Get(
        path: '/v1/admin/payments/export',
        summary: 'Export platform payments as CSV (admin)',
        description: 'Streams a CSV download. Rejects with 422 if the filtered result set exceeds 10,000 rows.',
        tags: ['Admin', 'Billing'],
        security: [['passport' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'pending', 'completed', 'failed'])),
            new OA\Parameter(name: 'purpose', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'subscription', 'boost', 'boosting', 'verification'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 120)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'CSV file stream',
                content: new OA\MediaType(mediaType: 'text/csv', schema: new OA\Schema(type: 'string', format: 'binary')),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Not an admin, or missing permission', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Too many rows to export, or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function export(Request $request): JsonResponse|StreamedResponse
    {
        try {
            $validated = $request->validate([
                'status' => ['sometimes', 'string', 'in:all,'.implode(',', PaymentStatus::values())],
                'purpose' => ['sometimes', 'string', 'in:all,subscription,boost,boosting,verification,wallet_top_up,wallet_topup'],
                'search' => ['sometimes', 'string', 'max:120'],
            ]);

            $rows = $this->adminPaymentService->exportRows($validated, self::EXPORT_ROW_LIMIT);

            if (count($rows) > self::EXPORT_ROW_LIMIT) {
                return sendResponse(
                    false,
                    'Too many payments to export at once. Narrow your filters (limit '.self::EXPORT_ROW_LIMIT.' rows).',
                    null,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $filename = 'finance-payments-'.now()->format('Y-m-d').'.csv';

            return response()->streamDownload(function () use ($rows): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }
                fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
                fputcsv($handle, $this->adminPaymentService->adminExportHeaders());
                foreach ($rows as $payment) {
                    foreach ($this->adminPaymentService->toAdminCsvRow($payment) as $csvRow) {
                        fputcsv($handle, $csvRow);
                    }
                }
                fclose($handle);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
