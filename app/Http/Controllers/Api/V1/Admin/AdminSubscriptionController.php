<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BusinessInfoResource;
use App\Models\BusinessInfo;
use App\Models\BusinessSubscription;
use App\Models\Payment;
use App\Services\AdminPaymentService;
use App\Services\PaymentReconciliationService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminSubscriptionController extends Controller
{
    public function __construct(
        private readonly PaymentReconciliationService $paymentReconciliation,
        private readonly SubscriptionService $subscriptionService,
        private readonly AdminPaymentService $adminPaymentService,
    ) {}

    public function grantPremium(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'business_id' => ['required', 'integer', 'exists:business_info,id'],
                'reason' => ['required', 'string', 'max:500'],
                'duration_days' => ['nullable', 'integer', 'min:1', 'max:730'],
                'paystack_reference' => ['nullable', 'string', 'max:255'],
                'payment_handling' => ['nullable', 'string', Rule::in(['waived', 'recorded'])],
                'payment_method' => ['nullable', 'string', Rule::in(['bank_transfer', 'cash', 'other'])],
                'payment_reference' => ['nullable', 'string', 'max:255'],
                'amount' => ['nullable', 'numeric', 'min:0'],
                'package_id' => ['nullable', 'string', 'max:100'],
            ]);

            if (($validated['payment_handling'] ?? null) === 'recorded' && empty($validated['payment_method'])) {
                return sendResponse(
                    false,
                    'Select a payment method when recording a payment (e.g. bank transfer).',
                    null,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $business = BusinessInfo::query()
                ->with(['subscription', 'user'])
                ->findOrFail((int) $validated['business_id']);

            $result = $this->paymentReconciliation->grantPremiumManually(
                $business,
                (string) $validated['reason'],
                $request->user('admin_api')?->id,
                isset($validated['duration_days']) ? (int) $validated['duration_days'] : null,
                isset($validated['paystack_reference']) ? (string) $validated['paystack_reference'] : null,
                [
                    'payment_handling' => $validated['payment_handling'] ?? null,
                    'payment_method' => $validated['payment_method'] ?? null,
                    'payment_reference' => $validated['payment_reference'] ?? null,
                    'amount' => array_key_exists('amount', $validated) ? (float) $validated['amount'] : null,
                    'package_id' => $validated['package_id'] ?? null,
                ],
            );

            $activatedBusiness = $result['business'];
            $activatedBusiness->load(['category:id,name,subcategories,icon', 'location:id,lga_name,state_name,city_name,country_name']);

            $message = ($validated['payment_handling'] ?? null) === 'waived'
                ? 'Premium activated with payment waived. Record saved on the payments page.'
                : 'Premium subscription activated successfully.';

            return sendResponse(true, $message, [
                'payment' => $this->adminPaymentService->toAdminDetail($result['payment']),
                'subscription' => $this->subscriptionService->subscriptionPayload($activatedBusiness),
                'business' => new BusinessInfoResource($activatedBusiness),
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Premium expiration tracker for admin follow-up / reactivation outreach.
     */
    public function expirationTracker(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'search' => ['nullable', 'string', 'max:255'],
                'urgency' => ['nullable', 'string', Rule::in(['all', 'active', 'expiring_soon', 'expired'])],
                'days_ahead' => ['nullable', 'integer', 'min:1', 'max:90'],
            ]);

            $page = max(1, (int) ($validated['page'] ?? 1));
            $perPage = max(1, min(100, (int) ($validated['per_page'] ?? 15)));
            $search = trim((string) ($validated['search'] ?? ''));
            $urgency = (string) ($validated['urgency'] ?? 'all');
            $daysAhead = max(1, (int) ($validated['days_ahead'] ?? 14));
            $now = now();
            $soonCutoff = $now->copy()->addDays($daysAhead);

            $baseQuery = BusinessSubscription::query()
                ->with([
                    'businessInfo:id,user_id,business_name,business_status,category_id',
                    'businessInfo.user:id,name,email,phone',
                    'businessInfo.category:id,name',
                ])
                ->where('plan', SubscriptionPlan::Premium)
                ->whereIn('status', [
                    SubscriptionStatus::Active,
                    SubscriptionStatus::Trialing,
                    SubscriptionStatus::Expired,
                ])
                ->whereNotNull('expires_at');

            $summaryQuery = clone $baseQuery;
            $summaryRows = $summaryQuery->get(['id', 'status', 'expires_at', 'trial_ends_at']);

            $summary = [
                'total_premium' => $summaryRows->count(),
                'active' => 0,
                'expiring_soon' => 0,
                'expired' => 0,
            ];

            foreach ($summaryRows as $row) {
                $expiresAt = $row->expires_at;
                if ($expiresAt === null) {
                    continue;
                }
                if ($expiresAt->lte($now) || $row->status === SubscriptionStatus::Expired) {
                    $summary['expired']++;
                } elseif ($expiresAt->lte($soonCutoff)) {
                    $summary['expiring_soon']++;
                } else {
                    $summary['active']++;
                }
            }

            $listQuery = clone $baseQuery;

            if ($search !== '') {
                $listQuery->whereHas('businessInfo', function ($query) use ($search): void {
                    $query->where(function ($inner) use ($search): void {
                        $inner->where('business_name', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($userQuery) use ($search): void {
                                $userQuery->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                    });
                });
            }

            if ($urgency === 'expired') {
                $listQuery->where(function ($query) use ($now): void {
                    $query->where('status', SubscriptionStatus::Expired)
                        ->orWhere('expires_at', '<=', $now);
                });
            } elseif ($urgency === 'expiring_soon') {
                $listQuery->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing])
                    ->where('expires_at', '>', $now)
                    ->where('expires_at', '<=', $soonCutoff);
            } elseif ($urgency === 'active') {
                $listQuery->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing])
                    ->where('expires_at', '>', $soonCutoff);
            }

            $listQuery->orderByRaw('CASE WHEN expires_at <= ? THEN 0 ELSE 1 END', [$now])
                ->orderBy('expires_at');

            $paginator = $listQuery->paginate($perPage, ['*'], 'page', $page);

            $items = $paginator->getCollection()->map(function (BusinessSubscription $subscription) use ($now, $soonCutoff): array {
                $business = $subscription->businessInfo;
                $expiresAt = $subscription->expires_at;
                $daysRemaining = $expiresAt !== null
                    ? (int) $now->copy()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false)
                    : null;

                $isExpired = $expiresAt === null
                    || $expiresAt->lte($now)
                    || $subscription->status === SubscriptionStatus::Expired;

                $urgencyKey = $isExpired
                    ? 'expired'
                    : ($expiresAt !== null && $expiresAt->lte($soonCutoff) ? 'expiring_soon' : 'active');

                return [
                    'subscription_id' => $subscription->id,
                    'business_id' => $business?->id,
                    'business_name' => $business?->business_name ?? '—',
                    'business_status' => $business?->business_status?->value ?? null,
                    'category' => $business?->category?->name ?? '—',
                    'vendor_name' => $business?->user?->name ?? '—',
                    'vendor_email' => $business?->user?->email ?? '',
                    'vendor_phone' => $business?->user?->phone ?? '',
                    'plan' => $subscription->plan->value,
                    'status' => $subscription->status->value,
                    'status_label' => $subscription->status->label(),
                    'is_trial' => $subscription->status === SubscriptionStatus::Trialing,
                    'expires_at' => $expiresAt?->toIso8601String(),
                    'expires_at_label' => $expiresAt ? humanDateTime($expiresAt) : null,
                    'days_remaining' => $daysRemaining,
                    'urgency' => $urgencyKey,
                    'needs_follow_up' => in_array($urgencyKey, ['expired', 'expiring_soon'], true),
                ];
            })->values()->all();

            return sendResponse(true, 'Premium expiration tracker loaded.', [
                'summary' => $summary,
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'filters' => [
                    'urgency' => $urgency,
                    'days_ahead' => $daysAhead,
                    'search' => $search,
                ],
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function reconcilePayment(Request $request, int $payment): JsonResponse
    {
        try {
            $validated = $request->validate([
                'paystack_reference' => ['nullable', 'string', 'max:255'],
            ]);

            $model = Payment::query()
                ->with(['user', 'businessInfo'])
                ->whereKey($payment)
                ->first();

            if ($model === null) {
                return sendResponse(false, 'Payment not found.', null, Response::HTTP_NOT_FOUND);
            }

            $reference = strtolower(trim((string) ($validated['paystack_reference'] ?? $model->tx_ref ?? '')));
            if ($reference === '') {
                return sendResponse(false, 'Paystack reference is required to reconcile this payment.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $result = $this->paymentReconciliation->reconcilePaystackReference($reference);

            $business = $result['business'];
            if ($business !== null) {
                $business->load(['category:id,name,subcategories,icon', 'location:id,lga_name,state_name,city_name,country_name']);
            }

            return sendResponse(true, 'Payment reconciled and premium activated.', [
                'payment' => $this->adminPaymentService->toAdminDetail($result['payment']),
                'subscription' => $business !== null
                    ? $this->subscriptionService->subscriptionPayload($business)
                    : null,
                'business' => $business !== null ? new BusinessInfoResource($business) : null,
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function applyPayment(Request $request, int $payment): JsonResponse
    {
        try {
            $validated = $request->validate([
                'gateway' => ['required', 'string', Rule::in(PaymentGateway::values())],
                'gateway_transaction_id' => ['required', 'string', 'max:255'],
                'reason' => ['nullable', 'string', 'max:500'],
                'verify_with_gateway' => ['nullable', 'boolean'],
            ]);

            $gateway = PaymentGateway::from((string) $validated['gateway']);
            $reason = trim((string) ($validated['reason'] ?? ''));

            if ($gateway === PaymentGateway::Flutterwave && $reason === '') {
                return sendResponse(
                    false,
                    'Reason is required when applying a Flutterwave payment manually.',
                    null,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $model = Payment::query()
                ->with(['user', 'businessInfo'])
                ->whereKey($payment)
                ->first();

            if ($model === null) {
                return sendResponse(false, 'Payment not found.', null, Response::HTTP_NOT_FOUND);
            }

            $result = $this->paymentReconciliation->adminApplyGatewayPayment(
                $model,
                $gateway,
                (string) $validated['gateway_transaction_id'],
                $reason !== '' ? $reason : null,
                $request->user('admin_api')?->id,
                array_key_exists('verify_with_gateway', $validated)
                    ? (bool) $validated['verify_with_gateway']
                    : null,
            );

            $business = $result['business'] ?? null;
            if ($business instanceof BusinessInfo) {
                $business->load(['category:id,name,subcategories,icon', 'location:id,lga_name,state_name,city_name,country_name']);
            }

            $message = match ($model->purpose) {
                PaymentPurpose::Subscription => 'Payment applied and premium activated.',
                PaymentPurpose::Verification => 'Verification payment applied successfully.',
                PaymentPurpose::Boost => 'Boost payment applied and queued for admin approval.',
                default => 'Payment applied successfully.',
            };

            return sendResponse(true, $message, [
                'payment' => $this->adminPaymentService->toAdminDetail($result['payment']),
                'subscription' => $business instanceof BusinessInfo && $model->purpose === PaymentPurpose::Subscription
                    ? $this->subscriptionService->subscriptionPayload($business)
                    : null,
                'verification' => $result['verification'] ?? null,
                'boost_request' => $result['boost_request'] ?? null,
                'business' => $business instanceof BusinessInfo ? new BusinessInfoResource($business) : null,
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function grantPayment(Request $request, int $payment): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => ['required', 'string', 'max:500'],
                'paystack_reference' => ['nullable', 'string', 'max:255'],
            ]);

            $model = Payment::query()
                ->with(['user', 'businessInfo'])
                ->whereKey($payment)
                ->first();

            if ($model === null) {
                return sendResponse(false, 'Payment not found.', null, Response::HTTP_NOT_FOUND);
            }

            $business = $model->businessInfo;
            if ($business === null) {
                return sendResponse(false, 'Payment is not linked to a business profile.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $adminId = $request->user('admin_api')?->id;
            $reason = trim((string) $validated['reason']);
            $paystackReference = isset($validated['paystack_reference'])
                ? trim((string) $validated['paystack_reference'])
                : null;

            $result = match ($model->purpose) {
                PaymentPurpose::Verification => $this->paymentReconciliation->grantVerificationManually(
                    $business,
                    $reason,
                    $adminId,
                    $paystackReference !== '' ? $paystackReference : null,
                    $model->id,
                ),
                PaymentPurpose::Boost => $this->paymentReconciliation->grantBoostManually(
                    $business,
                    $reason,
                    $adminId,
                    $paystackReference !== '' ? $paystackReference : null,
                    $model->id,
                ),
                PaymentPurpose::Subscription => (function () use ($business, $reason, $adminId, $paystackReference): array {
                    $grant = $this->paymentReconciliation->grantPremiumManually(
                        $business,
                        $reason,
                        $adminId,
                        null,
                        $paystackReference !== '' ? $paystackReference : null,
                    );

                    return [
                        'payment' => $grant['payment'],
                        'business' => $grant['business'],
                    ];
                })(),
                default => throw new RuntimeException('This payment type cannot be granted from the admin panel.'),
            };

            $business = $result['business'] ?? $business;
            if ($business instanceof BusinessInfo) {
                $business->load(['category:id,name,subcategories,icon', 'location:id,lga_name,state_name,city_name,country_name']);
            }

            $message = match ($model->purpose) {
                PaymentPurpose::Subscription => 'Premium granted successfully.',
                PaymentPurpose::Verification => 'Verification payment granted successfully.',
                PaymentPurpose::Boost => 'Boost payment granted and queued for admin approval.',
                default => 'Payment granted successfully.',
            };

            return sendResponse(true, $message, [
                'payment' => $this->adminPaymentService->toAdminDetail($result['payment']),
                'subscription' => $business instanceof BusinessInfo && $model->purpose === PaymentPurpose::Subscription
                    ? $this->subscriptionService->subscriptionPayload($business)
                    : null,
                'verification' => $result['verification'] ?? null,
                'boost_request' => $result['boost_request'] ?? null,
                'business' => $business instanceof BusinessInfo ? new BusinessInfoResource($business) : null,
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
