<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class VendorPaymentsController extends Controller
{
    private const EXPORT_ROW_LIMIT = 10_000;

    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    #[OA\Get(
        path: '/v1/vendor/payments',
        summary: 'List the vendor\'s payment history',
        tags: ['Billing'],
        security: [['passport' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
            new OA\Parameter(name: 'purpose', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['verification', 'boosting', 'subscription', 'wallet_topup'])),
            new OA\Parameter(name: 'month', in: 'query', required: false, description: 'Calendar month filter, format YYYY-MM.', schema: new OA\Schema(type: 'string', example: '2026-07')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payments retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Payment')),
                        new OA\Property(property: 'pagination', properties: [
                            new OA\Property(property: 'current_page', type: 'integer'),
                            new OA\Property(property: 'last_page', type: 'integer'),
                            new OA\Property(property: 'per_page', type: 'integer'),
                            new OA\Property(property: 'total', type: 'integer'),
                        ], type: 'object'),
                        new OA\Property(property: 'subscription_month_range', properties: [
                            new OA\Property(property: 'start_month', type: 'string'),
                            new OA\Property(property: 'end_month', type: 'string'),
                            new OA\Property(property: 'has_subscription_history', type: 'boolean'),
                        ], type: 'object'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Month filter outside allowed range, or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function index(Request $request)
    {
        try {
            $vendor = $request->user('api');

            $validated = $request->validate([
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'purpose' => ['sometimes', 'string', 'in:'.implode(',', PaymentPurpose::values())],
                /** Calendar month filter `YYYY-MM` (uses paid_at when set, otherwise created_at). */
                'month' => ['sometimes', 'string', 'regex:/^\d{4}-\d{2}$/'],
            ]);

            $perPage = (int) ($validated['per_page'] ?? 15);

            $subscriptionMonthRange = $this->subscriptionFilterMonthRange((int) $vendor->id);

            if ($reject = $this->rejectMonthOutsideSubscriptionRange($validated['month'] ?? null, $subscriptionMonthRange)) {
                return $reject;
            }

            $query = $this->basePaymentsQuery((int) $vendor->id);
            $this->applyOptionalPaymentFilters($query, $validated);

            $paginator = $query->paginate($perPage);

            $items = $paginator->getCollection()->map(fn (Payment $payment) => $this->paymentService->toVendorListItem($payment));

            return sendResponse(true, 'Payments retrieved successfully.', [
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'subscription_month_range' => $subscriptionMonthRange,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Get(
        path: '/v1/vendor/payments/export',
        summary: 'Export the vendor\'s payment history as CSV',
        description: 'Streams a CSV download. Rejects with 422 if the filtered result set exceeds 10,000 rows.',
        tags: ['Billing'],
        security: [['passport' => []]],
        parameters: [
            new OA\Parameter(name: 'purpose', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['verification', 'boosting', 'subscription', 'wallet_topup'])),
            new OA\Parameter(name: 'month', in: 'query', required: false, description: 'Calendar month filter, format YYYY-MM.', schema: new OA\Schema(type: 'string', example: '2026-07')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'CSV file stream',
                content: new OA\MediaType(mediaType: 'text/csv', schema: new OA\Schema(type: 'string', format: 'binary')),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Too many rows to export, or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function export(Request $request): JsonResponse|StreamedResponse
    {
        try {
            $vendor = $request->user('api');

            $validated = $request->validate([
                'purpose' => ['sometimes', 'string', 'in:'.implode(',', PaymentPurpose::values())],
                'month' => ['sometimes', 'string', 'regex:/^\d{4}-\d{2}$/'],
            ]);

            $subscriptionMonthRange = $this->subscriptionFilterMonthRange((int) $vendor->id);

            if ($reject = $this->rejectMonthOutsideSubscriptionRange($validated['month'] ?? null, $subscriptionMonthRange)) {
                return $reject;
            }

            $query = $this->basePaymentsQuery((int) $vendor->id);
            $this->applyOptionalPaymentFilters($query, $validated);

            $count = (clone $query)->count();
            if ($count > self::EXPORT_ROW_LIMIT) {
                return sendResponse(
                    false,
                    'Too many payments to export at once. Narrow the type or month filter (limit '.self::EXPORT_ROW_LIMIT.' rows).',
                    null,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $payments = $query->limit(self::EXPORT_ROW_LIMIT)->get();

            $filename = 'payment-history-'.now()->format('Y-m-d-His').'.csv';

            return response()->streamDownload(function () use ($payments): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }
                fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
                fputcsv($handle, $this->paymentService->vendorPaymentExportHeaders());
                foreach ($payments as $payment) {
                    fputcsv($handle, $this->paymentService->toVendorCsvRow($payment));
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

    private function basePaymentsQuery(int $userId): Builder
    {
        return Payment::query()
            ->where('user_id', $userId)
            ->orderByDesc('id');
    }

    /**
     * @param  array{purpose?: string, month?: string}  $validated
     */
    private function applyOptionalPaymentFilters(Builder $query, array $validated): void
    {
        if (! empty($validated['purpose'])) {
            $query->where('purpose', $validated['purpose']);
        }

        if (! empty($validated['month'])) {
            $start = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$start, $end]);
        }
    }

    /**
     * @param  array{start_month: string, end_month: string, has_subscription_history: bool}  $subscriptionMonthRange
     */
    private function rejectMonthOutsideSubscriptionRange(?string $month, array $subscriptionMonthRange): ?JsonResponse
    {
        if ($month === null || $month === '') {
            return null;
        }

        if ($month < $subscriptionMonthRange['start_month'] || $month > $subscriptionMonthRange['end_month']) {
            return sendResponse(false, 'Selected month is outside the allowed range for your subscription history.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    /**
     * Month bounds for the payment-history month filter: from the calendar month of the vendor's
     * first successful (completed) subscription payment through the current month. If none yet,
     * the range collapses to the current month only.
     *
     * @return array{start_month: string, end_month: string, has_subscription_history: bool}
     */
    private function subscriptionFilterMonthRange(int $userId): array
    {
        $nowMonth = Carbon::now()->startOfMonth();
        $endMonth = $nowMonth->format('Y-m');

        $firstPaidAt = Payment::query()
            ->where('user_id', $userId)
            ->where('purpose', PaymentPurpose::Subscription)
            ->where('status', PaymentStatus::Completed)
            ->whereNotNull('paid_at')
            ->orderBy('paid_at')
            ->value('paid_at');

        if ($firstPaidAt === null) {
            return [
                'start_month' => $endMonth,
                'end_month' => $endMonth,
                'has_subscription_history' => false,
            ];
        }

        $start = Carbon::parse($firstPaidAt)->startOfMonth();
        if ($start->gt($nowMonth)) {
            $start = $nowMonth->copy();
        }

        return [
            'start_month' => $start->format('Y-m'),
            'end_month' => $endMonth,
            'has_subscription_history' => true,
        ];
    }

    #[OA\Get(
        path: '/v1/vendor/payments/{payment}',
        summary: 'Get a single payment owned by the vendor',
        tags: ['Billing'],
        security: [['passport' => []]],
        parameters: [
            new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 4821),
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
            new OA\Response(response: 404, description: 'Payment not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function show(Request $request, int $payment)
    {
        try {
            $vendor = $request->user('api');

            $model = Payment::query()
                ->where('user_id', $vendor->id)
                ->whereKey($payment)
                ->first();

            if ($model === null) {
                return sendResponse(false, 'Payment not found.', null, Response::HTTP_NOT_FOUND);
            }

            return sendResponse(true, 'Payment retrieved successfully.', [
                'payment' => $this->paymentService->toVendorDetail($model),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
