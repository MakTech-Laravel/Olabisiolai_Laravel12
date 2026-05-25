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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class VendorPaymentsController extends Controller
{
    private const EXPORT_ROW_LIMIT = 10_000;

    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

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
