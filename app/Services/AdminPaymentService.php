<?php

namespace App\Services;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class AdminPaymentService
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * @param  array{
     *     page?: int,
     *     per_page?: int,
     *     status?: string,
     *     purpose?: string,
     *     search?: string,
     * }  $filters
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function paginate(array $filters): array
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 10)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        $query = $this->baseQuery();
        $this->applyFilters($query, $filters);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->flatMap(fn(Payment $payment) => $this->expandAdminListItems($payment))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @param  array{
     *     status?: string,
     *     purpose?: string,
     *     search?: string,
     * }  $filters
     * @return list<Payment>
     */
    public function exportRows(array $filters, int $limit = 10_000): array
    {
        $query = $this->baseQuery();
        $this->applyFilters($query, $filters);

        return $query->limit($limit)->get()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function analytics(string $trendRange = 'monthly'): array
    {
        $completed = Payment::query()->where('status', PaymentStatus::Completed);

        $totalRevenue = (float) (clone $completed)->sum('amount');
        $verificationRevenue = $this->sumCompletedByPurpose(PaymentPurpose::Verification);
        $subscriptionRevenue = $this->sumCompletedByPurpose(PaymentPurpose::Subscription);
        $boostRevenue = $this->sumCompletedByPurpose(PaymentPurpose::Boost);

        $breakdown = $this->revenueBreakdown($totalRevenue, [
            'subscription' => $subscriptionRevenue,
            'boost' => $boostRevenue,
            'verification' => $verificationRevenue,
        ]);

        return [
            'overview' => [
                'total_revenue' => $totalRevenue,
                'verification_revenue' => $verificationRevenue,
                'subscription_revenue' => $subscriptionRevenue,
                'boost_revenue' => $boostRevenue,
                'total_growth_percent' => $this->growthPercentForAllPurposes(),
                'verification_growth_percent' => $this->growthPercentForPurpose(PaymentPurpose::Verification),
                'subscription_growth_percent' => $this->growthPercentForPurpose(PaymentPurpose::Subscription),
                'boost_growth_percent' => $this->growthPercentForPurpose(PaymentPurpose::Boost),
            ],
            'trend' => $this->trendSeries($trendRange),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function expandAdminListItems(Payment $payment): array
    {
        if (! $this->paymentService->hasWalletGatewaySplit($payment)) {
            $item = $this->toAdminListItem($payment);
            $item['list_key'] = (string) $payment->id;

            return [$item];
        }

        $split = $this->paymentService->walletSplitFromPayment($payment);
        $base = $this->toAdminListItem($payment);

        return [
            array_merge($base, [
                'amount' => $split['wallet_applied'],
                'method' => 'wallet',
                'reference' => $payment->tx_ref . '-wallet',
                'reference_display' => strtoupper((string) $payment->tx_ref) . '-WALLET',
                'list_key' => $payment->id . '-wallet',
                'split_part' => 'wallet',
            ]),
            array_merge($base, [
                'amount' => $split['gateway_amount'],
                'method' => $this->resolveGatewayCardMethod($payment),
                'list_key' => $payment->id . '-gateway',
                'split_part' => 'gateway',
            ]),
        ];
    }

    /**
     * Payment history for a single business (admin business detail).
     *
     * @return array{
     *     summary: array{
     *         total_transactions: int,
     *         completed_transactions: int,
     *         pending_transactions: int,
     *         failed_transactions: int,
     *         total_amount_completed: float,
     *     },
     *     items: list<array<string, mixed>>,
     * }
     */
    public function historyForBusiness(int $businessInfoId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        $base = Payment::query()->where('business_info_id', $businessInfoId);

        $summary = [
            'total_transactions' => (clone $base)->count(),
            'completed_transactions' => (clone $base)->where('status', PaymentStatus::Completed)->count(),
            'pending_transactions' => (clone $base)->where('status', PaymentStatus::Pending)->count(),
            'failed_transactions' => (clone $base)->where('status', PaymentStatus::Failed)->count(),
            'total_amount_completed' => (float) (clone $base)
                ->where('status', PaymentStatus::Completed)
                ->sum('amount'),
        ];

        $payments = (clone $base)
            ->with(['user', 'businessInfo'])
            ->orderByRaw('COALESCE(paid_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $items = $payments
            ->flatMap(function (Payment $payment): array {
                return array_map(
                    function (array $item) use ($payment): array {
                        $meta = is_array($payment->metadata) ? $payment->metadata : [];
                        $packageId = (string) ($payment->package_id ?? '');
                        $packageTitle = is_string($meta['package_title'] ?? null)
                            ? (string) $meta['package_title']
                            : null;

                        return array_merge($item, [
                            'manual_grant' => (bool) ($meta['manual_grant'] ?? false),
                            'metadata' => $meta,
                            'purpose_label' => $payment->purpose->label(),
                            'package_id' => $packageId !== '' ? $packageId : null,
                            'package_label' => $this->resolvePackageLabel($packageId, $packageTitle),
                        ]);
                    },
                    $this->expandAdminListItems($payment),
                );
            })
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminListItem(Payment $payment): array
    {
        $payment->loadMissing(['user', 'businessInfo']);

        $occurredAt = $payment->paid_at ?? $payment->created_at;
        $user = $payment->user;
        $business = $payment->businessInfo;

        return [
            'id' => $payment->id,
            'business' => $business?->business_name ?? '—',
            'payer_name' => $user?->name ?? '—',
            'payer_email' => $user?->email ?? '',
            'reference' => (string) $payment->tx_ref,
            'reference_display' => strtoupper((string) $payment->tx_ref),
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'method' => $this->resolvePaymentMethod($payment),
            'status' => $payment->status->value,
            'purpose' => $payment->purpose->value,
            'transaction_type' => $this->transactionType($payment->purpose),
            'date_short' => $occurredAt ? humanDateTime($occurredAt, 'M j, h:i A') : '',
            'date_time_long' => $occurredAt ? humanDateTime($occurredAt, 'F j, Y \a\t h:i A') : '',
            'paid_at_iso' => $payment->paid_at?->toIso8601String(),
            'created_at_iso' => $payment->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminDetail(Payment $payment): array
    {
        return array_merge($this->toAdminListItem($payment), [
            'tx_ref' => (string) $payment->tx_ref,
            'business_id' => $payment->business_info_id,
            'user_id' => $payment->user_id,
            'purpose_label' => $payment->purpose->label(),
            'gateway_transaction_id' => $payment->gateway_transaction_id,
            'package_id' => $payment->package_id,
            'is_consumed' => $payment->is_consumed,
            'metadata' => $payment->metadata,
        ]);
    }

    /**
     * @return list<string>
     */
    public function adminExportHeaders(): array
    {
        return [
            'Business',
            'Payer',
            'Email',
            'Reference',
            'Amount (NGN)',
            'Type',
            'Method',
            'Status',
            'Date',
        ];
    }

    /**
     * @return list<string>
     */
    public function toAdminCsvRow(Payment $payment): array
    {
        return array_map(
            fn(array $item): array => [
                $item['business'],
                $item['payer_name'],
                $item['payer_email'],
                $item['reference'],
                (string) $item['amount'],
                $item['transaction_type'],
                $item['method'],
                $item['status'],
                $item['date_short'],
            ],
            $this->expandAdminListItems($payment),
        );
    }

    private function baseQuery(): Builder
    {
        return Payment::query()
            ->with(['user', 'businessInfo'])
            ->orderByRaw('COALESCE(paid_at, created_at) DESC')
            ->orderByDesc('id');
    }

    /**
     * @param  array{
     *     status?: string,
     *     purpose?: string,
     *     search?: string,
     * }  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['purpose']) && $filters['purpose'] !== 'all') {
            $purpose = $this->resolvePurposeFilter((string) $filters['purpose']);
            if ($purpose !== null) {
                $query->where('purpose', $purpose->value);
            }
        }

        if (! empty($filters['search'])) {
            $term = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('tx_ref', 'like', $term)
                    ->orWhereHas('user', fn(Builder $userQuery) => $userQuery
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term))
                    ->orWhereHas('businessInfo', fn(Builder $businessQuery) => $businessQuery
                        ->where('business_name', 'like', $term));
            });
        }
    }

    private function resolvePurposeFilter(string $tab): ?PaymentPurpose
    {
        return match ($tab) {
            'subscription' => PaymentPurpose::Subscription,
            'boost', 'boosting' => PaymentPurpose::Boost,
            'verification' => PaymentPurpose::Verification,
            'wallet_top_up', 'wallet_topup' => PaymentPurpose::WalletTopUp,
            default => null,
        };
    }

    private function transactionType(PaymentPurpose $purpose): string
    {
        return match ($purpose) {
            PaymentPurpose::Boost => 'boost',
            PaymentPurpose::Subscription => 'subscription',
            PaymentPurpose::Verification => 'verification',
            PaymentPurpose::WalletTopUp => 'wallet_top_up',
        };
    }

    private function resolvePackageLabel(?string $packageId, ?string $packageTitle = null): ?string
    {
        $packageId = strtolower(trim((string) $packageId));
        if ($packageId === '') {
            return null;
        }

        if (str_contains($packageId, 'yearly') || str_contains($packageId, 'annual')) {
            return 'Yearly';
        }

        if (str_contains($packageId, 'monthly')) {
            return 'Monthly';
        }

        if (str_contains($packageId, 'quarterly')) {
            return 'Quarterly';
        }

        if (str_contains($packageId, 'lifetime')) {
            return 'Lifetime';
        }

        $title = trim((string) $packageTitle);
        if ($title !== '') {
            return $title;
        }

        return str_replace('_', ' ', $packageId);
    }

    private function resolvePaymentMethod(Payment $payment): string
    {
        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $raw = strtolower((string) ($meta['payment_method'] ?? $meta['payment_type'] ?? $meta['payment_option'] ?? ''));

        if (! empty($meta['payment_waived']) || str_contains($raw, 'waiv')) {
            return 'waived';
        }

        if ($payment->gateway === PaymentGateway::Wallet) {
            return 'wallet';
        }

        if (
            $payment->gateway === PaymentGateway::Paystack
            || $payment->gateway === PaymentGateway::Flutterwave
        ) {
            return 'card';
        }

        if (str_contains($raw, 'bank') || str_contains($raw, 'transfer')) {
            return 'bank_transfer';
        }

        if (str_contains($raw, 'cash')) {
            return 'bank_transfer';
        }

        if (str_contains($raw, 'wallet')) {
            return 'wallet';
        }

        return 'card';
    }

    private function resolveGatewayCardMethod(Payment $payment): string
    {
        return $this->resolvePaymentMethod($payment) === 'wallet' ? 'card' : $this->resolvePaymentMethod($payment);
    }

    private function sumCompletedByPurpose(PaymentPurpose $purpose): float
    {
        return (float) Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->where('purpose', $purpose)
            ->sum('amount');
    }

    /**
     * @param  array{subscription: float, boost: float, verification: float}  $byPurpose
     * @return list<array{label: string, width_percent: int}>
     */
    private function revenueBreakdown(float $total, array $byPurpose): array
    {
        $items = [
            ['label' => 'Subscriptions', 'amount' => $byPurpose['subscription']],
            ['label' => 'Boosts', 'amount' => $byPurpose['boost']],
            ['label' => 'Verifications', 'amount' => $byPurpose['verification']],
        ];

        return array_map(function (array $item) use ($total): array {
            $percent = $total > 0 ? (int) round(($item['amount'] / $total) * 100) : 0;

            return [
                'label' => $item['label'],
                'width_percent' => $percent,
            ];
        }, $items);
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    private function trendSeries(string $range): array
    {
        if ($range === 'yearly') {
            $start = Carbon::now()->startOfYear()->subYears(5);
            $points = [];

            for ($i = 0; $i < 6; $i++) {
                $yearStart = $start->copy()->addYears($i);
                $yearEnd = $yearStart->copy()->endOfYear();
                $points[] = [
                    'label' => $yearStart->format('Y'),
                    'value' => $this->sumCompletedBetween($yearStart, $yearEnd),
                ];
            }

            return $points;
        }

        $start = Carbon::now()->startOfMonth()->subMonths(5);
        $points = [];

        for ($i = 0; $i < 6; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $points[] = [
                'label' => $monthStart->format('M'),
                'value' => $this->sumCompletedBetween($monthStart, $monthEnd),
            ];
        }

        return $points;
    }

    private function sumCompletedBetween(Carbon $start, Carbon $end): float
    {
        return (float) Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$start, $end])
            ->sum('amount');
    }

    private function growthPercentForAllPurposes(): ?int
    {
        return $this->growthPercentBetweenPeriods(
            $this->sumCompletedInLastDays(30),
            $this->sumCompletedInPreviousDays(30, 30),
        );
    }

    private function growthPercentForPurpose(PaymentPurpose $purpose): ?int
    {
        return $this->growthPercentBetweenPeriods(
            $this->sumCompletedInLastDays(30, $purpose),
            $this->sumCompletedInPreviousDays(30, 30, $purpose),
        );
    }

    private function sumCompletedInLastDays(int $days, ?PaymentPurpose $purpose = null): float
    {
        $end = Carbon::now();
        $start = $end->copy()->subDays($days);

        return $this->sumCompletedInWindow($start, $end, $purpose);
    }

    private function sumCompletedInPreviousDays(int $offsetDays, int $windowDays, ?PaymentPurpose $purpose = null): float
    {
        $end = Carbon::now()->subDays($offsetDays);
        $start = $end->copy()->subDays($windowDays);

        return $this->sumCompletedInWindow($start, $end, $purpose);
    }

    private function sumCompletedInWindow(Carbon $start, Carbon $end, ?PaymentPurpose $purpose): float
    {
        $query = Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$start, $end]);

        if ($purpose !== null) {
            $query->where('purpose', $purpose);
        }

        return (float) $query->sum('amount');
    }

    private function growthPercentBetweenPeriods(float $current, float $previous): ?int
    {
        if ($previous <= 0) {
            return $current > 0 ? 100 : null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }
}
