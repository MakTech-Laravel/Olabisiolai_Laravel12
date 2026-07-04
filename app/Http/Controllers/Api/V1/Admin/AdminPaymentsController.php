<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AdminPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminPaymentsController extends Controller
{
    private const EXPORT_ROW_LIMIT = 10_000;

    public function __construct(
        private readonly AdminPaymentService $adminPaymentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => ['sometimes', 'integer', 'min:1'],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'status' => ['sometimes', 'string', 'in:all,' . implode(',', PaymentStatus::values())],
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

    public function export(Request $request): JsonResponse|StreamedResponse
    {
        try {
            $validated = $request->validate([
                'status' => ['sometimes', 'string', 'in:all,' . implode(',', PaymentStatus::values())],
                'purpose' => ['sometimes', 'string', 'in:all,subscription,boost,boosting,verification,wallet_top_up,wallet_topup'],
                'search' => ['sometimes', 'string', 'max:120'],
            ]);

            $rows = $this->adminPaymentService->exportRows($validated, self::EXPORT_ROW_LIMIT);

            if (count($rows) > self::EXPORT_ROW_LIMIT) {
                return sendResponse(
                    false,
                    'Too many payments to export at once. Narrow your filters (limit ' . self::EXPORT_ROW_LIMIT . ' rows).',
                    null,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $filename = 'finance-payments-' . now()->format('Y-m-d') . '.csv';

            return response()->streamDownload(function () use ($rows): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($handle, $this->adminPaymentService->adminExportHeaders());
                foreach ($rows as $payment) {
                    fputcsv($handle, $this->adminPaymentService->toAdminCsvRow($payment));
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
