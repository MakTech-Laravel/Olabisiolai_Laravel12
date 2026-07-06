<?php

namespace App\Services;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\BusinessInfo;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentReconciliationService
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaystackService $paystackService,
        private readonly SubscriptionService $subscriptionService,
        private readonly BoostPurchaseService $boostPurchaseService,
        private readonly VerificationService $verificationService,
    ) {}

    public function findPaymentByGatewayReference(string $reference): ?Payment
    {
        $reference = strtolower(trim($reference));
        if ($reference === '') {
            return null;
        }

        return Payment::query()
            ->whereRaw('LOWER(tx_ref) = ?', [$reference])
            ->orWhereRaw('LOWER(gateway_transaction_id) = ?', [$reference])
            ->latest('id')
            ->first();
    }

    /**
     * Verify Paystack payment and complete subscription checkout (confirm + activate premium).
     */
    public function completeSubscriptionCheckout(
        Payment $payment,
        User $vendor,
        string $gatewayTransactionId,
        PaymentGateway $gateway,
        bool $verifyWithGateway = true,
    ): BusinessInfo {
        if ($payment->purpose !== PaymentPurpose::Subscription) {
            throw new RuntimeException('Only subscription payments can activate premium.');
        }

        if ($payment->user_id !== $vendor->id) {
            throw new RuntimeException('Payment does not belong to this vendor.');
        }

        $gatewayTransactionId = strtolower(trim($gatewayTransactionId));
        if ($gatewayTransactionId === '') {
            throw new RuntimeException('Payment reference is required.');
        }

        $business = $payment->businessInfo;
        if ($business === null) {
            throw new RuntimeException('Payment is not linked to a business profile.');
        }

        if ($this->subscriptionService->hasActivePremium($business)) {
            return $business->fresh(['subscription']);
        }

        if ($verifyWithGateway && $gateway === PaymentGateway::Paystack) {
            $this->verifyPaystackForSubscriptionPayment($payment, $gatewayTransactionId);
        }

        if ($payment->status === PaymentStatus::Pending) {
            $this->paymentService->assertGatewayTransactionAvailable($gatewayTransactionId, $payment);
            $this->paymentService->confirmBundledPayments($payment, $gatewayTransactionId, $gateway);
            $payment = $payment->fresh();
        } elseif ($payment->gateway_transaction_id === null && $gatewayTransactionId !== '') {
            $payment->update([
                'gateway' => $gateway,
                'gateway_transaction_id' => $gatewayTransactionId,
            ]);
            $payment = $payment->fresh();
        } elseif ($payment->gateway === null) {
            $payment->update(['gateway' => $gateway]);
            $payment = $payment->fresh();
        }

        return $this->subscriptionService->activatePremiumAfterPayment($payment, $vendor);
    }

    /**
     * Reconcile a Paystack charge by reference (webhook or admin/vendor retry).
     *
     * @return array{payment: Payment, business: BusinessInfo|null, activated: bool}
     */
    public function reconcilePaystackReference(string $reference, ?BusinessInfo $forBusiness = null): array
    {
        $reference = strtolower(trim($reference));
        if ($reference === '') {
            throw new RuntimeException('Paystack reference is required.');
        }

        $paystackData = $this->verifyPaystackWithRetry($reference);
        if (strtolower((string) ($paystackData['status'] ?? '')) !== 'success') {
            throw new RuntimeException('Paystack reports this payment was not successful.');
        }

        $payment = $this->findPaymentByGatewayReference($reference);
        if ($payment === null) {
            $metadataReference = (string) ($paystackData['reference'] ?? $reference);
            $payment = $this->findPaymentByGatewayReference($metadataReference);
        }

        if ($payment === null) {
            throw new RuntimeException('No matching checkout was found for this Paystack reference.');
        }

        if ($payment->purpose !== PaymentPurpose::Subscription) {
            throw new RuntimeException('This Paystack reference is not linked to a premium subscription checkout.');
        }

        if ($forBusiness !== null && $payment->business_info_id !== $forBusiness->id) {
            throw new RuntimeException('This payment reference belongs to a different business checkout.');
        }

        $vendor = $payment->user;
        if ($vendor === null) {
            throw new RuntimeException('Payment is missing the vendor account.');
        }

        $this->assertPaystackAmountMatchesSubscriptionCheckout($payment, $paystackData);

        $paystackReference = (string) ($paystackData['reference'] ?? $reference);

        if ($payment->status === PaymentStatus::Completed && $payment->is_consumed) {
            $business = $payment->businessInfo;

            return [
                'payment' => $payment->fresh(),
                'business' => $business,
                'activated' => $business !== null && $this->subscriptionService->hasActivePremium($business),
            ];
        }

        $this->paymentService->assertGatewayTransactionAvailable($paystackReference, $payment);

        $business = $this->completeSubscriptionCheckout(
            $payment,
            $vendor,
            $paystackReference,
            PaymentGateway::Paystack,
            verifyWithGateway: false,
        );

        Log::info('subscription.payment.reconciled', [
            'payment_id' => $payment->id,
            'user_id' => $vendor->id,
            'business_id' => $business->id,
            'paystack_reference' => $reference,
        ]);

        return [
            'payment' => $payment->fresh(),
            'business' => $business,
            'activated' => $this->subscriptionService->hasActivePremium($business),
        ];
    }

    /**
     * Admin applies a gateway transaction to a pending payment (subscription, verification, or boost).
     *
     * @return array<string, mixed>
     */
    public function adminApplyGatewayPayment(
        Payment $payment,
        PaymentGateway $gateway,
        string $gatewayTransactionId,
        ?string $reason = null,
        ?int $adminId = null,
        ?bool $verifyWithGateway = null,
    ): array {
        $gatewayTransactionId = trim($gatewayTransactionId);
        if ($gatewayTransactionId === '') {
            throw new RuntimeException('Gateway transaction ID is required.');
        }

        $reason = trim((string) $reason);
        $verify = $verifyWithGateway ?? ($gateway === PaymentGateway::Paystack);

        if ($gateway === PaymentGateway::Flutterwave) {
            $verify = false;
            if ($reason === '') {
                throw new RuntimeException('Reason is required when applying a Flutterwave payment manually.');
            }
        }

        if ($verify) {
            $paystackData = $this->verifyPaystackWithRetry(strtolower($gatewayTransactionId));
            if (strtolower((string) ($paystackData['status'] ?? '')) !== 'success') {
                throw new RuntimeException('Paystack reports this payment was not successful.');
            }
            $this->assertPaystackAmountForPayment($payment, $paystackData);
            $gatewayTransactionId = (string) ($paystackData['reference'] ?? $gatewayTransactionId);
        }

        if ($payment->status === PaymentStatus::Pending) {
            $this->paymentService->assertGatewayTransactionAvailable($gatewayTransactionId, $payment);
        }

        return match ($payment->purpose) {
            PaymentPurpose::Subscription => $this->adminApplySubscriptionPayment(
                $payment,
                $gateway,
                $gatewayTransactionId,
                $reason,
                $adminId,
                false,
            ),
            PaymentPurpose::Verification => $this->adminApplyVerificationPayment(
                $payment,
                $gateway,
                $gatewayTransactionId,
                $reason,
                $adminId,
            ),
            PaymentPurpose::Boost => $this->adminApplyBoostPayment(
                $payment,
                $gateway,
                $gatewayTransactionId,
                $reason,
                $adminId,
            ),
            default => throw new RuntimeException('This payment type cannot be applied from the admin panel.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function adminApplySubscriptionPayment(
        Payment $payment,
        PaymentGateway $gateway,
        string $gatewayTransactionId,
        ?string $reason,
        ?int $adminId,
        bool $verifyWithGateway,
    ): array {
        $vendor = $payment->user;
        if ($vendor === null) {
            throw new RuntimeException('Payment is missing the vendor account.');
        }

        if ($payment->status === PaymentStatus::Pending && $reason !== '') {
            $payment->update([
                'metadata' => array_merge(is_array($payment->metadata) ? $payment->metadata : [], [
                    'admin_apply_reason' => $reason,
                    'applied_by_admin_id' => $adminId,
                    'applied_at' => now()->toIso8601String(),
                ]),
            ]);
        }

        $business = $this->completeSubscriptionCheckout(
            $payment->fresh(),
            $vendor,
            $gatewayTransactionId,
            $gateway,
            $verifyWithGateway,
        );

        return [
            'payment' => $payment->fresh(),
            'business' => $business,
            'subscription' => $this->subscriptionService->subscriptionPayload($business),
            'purpose' => $payment->purpose->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminApplyVerificationPayment(
        Payment $payment,
        PaymentGateway $gateway,
        string $gatewayTransactionId,
        string $reason,
        ?int $adminId,
    ): array {
        $business = $payment->businessInfo;
        if ($business === null) {
            throw new RuntimeException('Payment is not linked to a business profile.');
        }

        if ($payment->status === PaymentStatus::Pending) {
            $payment = $this->paymentService->confirmPayment($payment, $gatewayTransactionId, $gateway);
            $payment->update([
                'metadata' => array_merge(is_array($payment->metadata) ? $payment->metadata : [], [
                    'admin_apply_reason' => $reason,
                    'applied_by_admin_id' => $adminId,
                    'applied_at' => now()->toIso8601String(),
                    'manual_gateway_apply' => true,
                ]),
            ]);
            $payment = $payment->fresh();
        } elseif ($payment->gateway_transaction_id === null) {
            $payment->update([
                'gateway' => $gateway,
                'gateway_transaction_id' => $gatewayTransactionId,
            ]);
            $payment = $payment->fresh();
        }

        Log::info('verification.payment.applied_by_admin', [
            'payment_id' => $payment->id,
            'business_id' => $business->id,
            'admin_id' => $adminId,
        ]);

        return [
            'payment' => $payment,
            'business' => $business->fresh(),
            'verification' => $this->verificationService->getVendorVerificationStatus($business->fresh()),
            'purpose' => $payment->purpose->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminApplyBoostPayment(
        Payment $payment,
        PaymentGateway $gateway,
        string $gatewayTransactionId,
        string $reason,
        ?int $adminId,
    ): array {
        $business = $payment->businessInfo;
        if ($business === null) {
            throw new RuntimeException('Payment is not linked to a business profile.');
        }

        if ($payment->status === PaymentStatus::Pending) {
            $payment = $this->paymentService->confirmPayment($payment, $gatewayTransactionId, $gateway);
            $payment->update([
                'metadata' => array_merge(is_array($payment->metadata) ? $payment->metadata : [], [
                    'admin_apply_reason' => $reason,
                    'applied_by_admin_id' => $adminId,
                    'applied_at' => now()->toIso8601String(),
                    'manual_gateway_apply' => true,
                ]),
            ]);
            $payment = $payment->fresh();
        } elseif ($payment->gateway_transaction_id === null) {
            $payment->update([
                'gateway' => $gateway,
                'gateway_transaction_id' => $gatewayTransactionId,
            ]);
            $payment = $payment->fresh();
        }

        $boostRequest = $this->boostPurchaseService->markPaidAndQueueForAdmin($payment);
        if ($boostRequest === null) {
            throw new RuntimeException('Boost request was not found for this payment.');
        }

        Log::info('boost.payment.applied_by_admin', [
            'payment_id' => $payment->id,
            'boost_request_id' => $boostRequest->id,
            'business_id' => $business->id,
            'admin_id' => $adminId,
        ]);

        return [
            'payment' => $payment,
            'business' => $business->fresh(),
            'boost_request' => [
                'id' => $boostRequest->id,
                'status' => $boostRequest->status->value,
            ],
            'purpose' => $payment->purpose->value,
        ];
    }

    /**
     * Admin manual premium grant (goodwill or after offline verification).
     *
     * @return array{payment: Payment, business: BusinessInfo}
     */
    public function grantPremiumManually(
        BusinessInfo $business,
        string $reason,
        ?int $grantedByAdminId = null,
        ?int $durationDays = null,
        ?string $paystackReference = null,
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A reason is required for manual premium activation.');
        }

        $vendor = $business->user;
        if ($vendor === null) {
            throw new RuntimeException('Business has no owner account.');
        }

        if ($paystackReference !== null && trim($paystackReference) !== '') {
            $result = $this->reconcilePaystackReference(trim($paystackReference));

            return [
                'payment' => $result['payment'],
                'business' => $result['business'],
            ];
        }

        $pendingPayment = Payment::query()
            ->where('business_info_id', $business->id)
            ->where('purpose', PaymentPurpose::Subscription)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($pendingPayment instanceof Payment) {
            return $this->completeAdminVerifiedPendingPayment(
                $pendingPayment,
                $vendor,
                $business,
                $reason,
                $grantedByAdminId,
                $durationDays,
            );
        }

        if ($this->subscriptionService->hasActivePremium($business)) {
            throw new RuntimeException('Premium is already active for this business.');
        }

        $durationDays = max(1, $durationDays ?? $this->subscriptionService->subscriptionDurationDays());
        $expiresAt = now()->addDays($durationDays);

        return DB::transaction(function () use ($business, $vendor, $reason, $grantedByAdminId, $expiresAt, $durationDays): array {
            $package = $this->paymentService->findPackage(PaymentPurpose::Subscription, 'premium_yearly');
            $amount = (float) ($package['amount'] ?? 0);

            $payment = Payment::query()->create([
                'user_id' => $vendor->id,
                'business_info_id' => $business->id,
                'purpose' => PaymentPurpose::Subscription,
                'package_id' => 'premium_yearly',
                'amount' => $amount,
                'currency' => config('subscription.currency', 'NGN'),
                'tx_ref' => sprintf('admin_grant_%s_%s', $vendor->id, strtolower(\Illuminate\Support\Str::random(10))),
                'gateway' => PaymentGateway::Paystack,
                'gateway_transaction_id' => 'admin_manual_' . now()->timestamp,
                'status' => PaymentStatus::Completed,
                'paid_at' => now(),
                'is_consumed' => false,
                'metadata' => [
                    'package_title' => $package['title'] ?? 'Premium Yearly',
                    'line_item' => 'subscription',
                    'manual_grant' => true,
                    'grant_reason' => $reason,
                    'granted_by_admin_id' => $grantedByAdminId,
                    'granted_at' => now()->toIso8601String(),
                ],
            ]);

            $this->subscriptionService->preparePremiumCheckout($business->fresh(['subscription']));
            $activated = $this->subscriptionService->activatePremiumAfterPayment($payment, $vendor);

            if ($durationDays !== $this->subscriptionService->subscriptionDurationDays()) {
                $activated->subscription?->update(['expires_at' => $expiresAt]);
                $activated = $activated->fresh(['subscription']);
            }

            Log::info('subscription.premium.granted_manually', [
                'payment_id' => $payment->id,
                'business_id' => $activated->id,
                'user_id' => $vendor->id,
                'granted_by_admin_id' => $grantedByAdminId,
                'reason' => $reason,
            ]);

            return [
                'payment' => $payment->fresh(),
                'business' => $activated,
            ];
        });
    }

    /**
     * Admin manual verification grant (pending checkout or Paystack reference).
     *
     * @return array{payment: Payment, business: BusinessInfo, verification: array<string, mixed>}
     */
    public function grantVerificationManually(
        BusinessInfo $business,
        string $reason,
        ?int $grantedByAdminId = null,
        ?string $paystackReference = null,
        ?int $paymentId = null,
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A reason is required for manual verification activation.');
        }

        $vendor = $business->user;
        if ($vendor === null) {
            throw new RuntimeException('Business has no owner account.');
        }

        if ($paystackReference !== null && trim($paystackReference) !== '') {
            $payment = $this->resolveVerificationPaymentForManualGrant($business, $paymentId);
            $result = $this->adminApplyGatewayPayment(
                $payment,
                PaymentGateway::Paystack,
                trim($paystackReference),
                $reason,
                $grantedByAdminId,
                true,
            );

            return [
                'payment' => $result['payment'],
                'business' => $result['business'],
                'verification' => $result['verification'],
            ];
        }

        $payment = $this->resolveVerificationPaymentForManualGrant($business, $paymentId);

        return $this->completeAdminVerifiedPendingVerificationPayment(
            $payment,
            $vendor,
            $business,
            $reason,
            $grantedByAdminId,
        );
    }

    /**
     * Admin manual boost grant (pending checkout or Paystack reference).
     *
     * @return array{payment: Payment, business: BusinessInfo, boost_request: array<string, mixed>|null}
     */
    public function grantBoostManually(
        BusinessInfo $business,
        string $reason,
        ?int $grantedByAdminId = null,
        ?string $paystackReference = null,
        ?int $paymentId = null,
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A reason is required for manual boost activation.');
        }

        $vendor = $business->user;
        if ($vendor === null) {
            throw new RuntimeException('Business has no owner account.');
        }

        if ($paystackReference !== null && trim($paystackReference) !== '') {
            $payment = $this->resolveBoostPaymentForManualGrant($business, $paymentId);
            $result = $this->adminApplyGatewayPayment(
                $payment,
                PaymentGateway::Paystack,
                trim($paystackReference),
                $reason,
                $grantedByAdminId,
                true,
            );

            return [
                'payment' => $result['payment'],
                'business' => $result['business'],
                'boost_request' => $result['boost_request'] ?? null,
            ];
        }

        $payment = $this->resolveBoostPaymentForManualGrant($business, $paymentId);

        return $this->completeAdminVerifiedPendingBoostPayment(
            $payment,
            $vendor,
            $business,
            $reason,
            $grantedByAdminId,
        );
    }

    private function resolveVerificationPaymentForManualGrant(BusinessInfo $business, ?int $paymentId): Payment
    {
        if ($paymentId !== null) {
            $payment = Payment::query()
                ->whereKey($paymentId)
                ->where('business_info_id', $business->id)
                ->where('purpose', PaymentPurpose::Verification)
                ->first();

            if ($payment === null) {
                throw new RuntimeException('Verification payment not found for this business.');
            }

            return $payment;
        }

        $payment = Payment::query()
            ->where('business_info_id', $business->id)
            ->where('purpose', PaymentPurpose::Verification)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($payment === null) {
            throw new RuntimeException('No pending verification checkout was found for this business.');
        }

        return $payment;
    }

    private function resolveBoostPaymentForManualGrant(BusinessInfo $business, ?int $paymentId): Payment
    {
        if ($paymentId !== null) {
            $payment = Payment::query()
                ->whereKey($paymentId)
                ->where('business_info_id', $business->id)
                ->where('purpose', PaymentPurpose::Boost)
                ->first();

            if ($payment === null) {
                throw new RuntimeException('Boost payment not found for this business.');
            }

            return $payment;
        }

        $payment = Payment::query()
            ->where('business_info_id', $business->id)
            ->where('purpose', PaymentPurpose::Boost)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($payment === null) {
            throw new RuntimeException('No pending boost checkout was found for this business.');
        }

        return $payment;
    }

    /**
     * @return array{payment: Payment, business: BusinessInfo, verification: array<string, mixed>}
     */
    private function completeAdminVerifiedPendingVerificationPayment(
        Payment $payment,
        User $vendor,
        BusinessInfo $business,
        string $reason,
        ?int $grantedByAdminId,
    ): array {
        if ($payment->purpose !== PaymentPurpose::Verification) {
            throw new RuntimeException('Only verification payments can be granted manually.');
        }

        if ($payment->user_id !== $vendor->id) {
            throw new RuntimeException('Payment does not belong to this vendor.');
        }

        return DB::transaction(function () use ($payment, $business, $reason, $grantedByAdminId): array {
            if ($payment->status === PaymentStatus::Pending) {
                $payment->update([
                    'status' => PaymentStatus::Completed,
                    'gateway' => PaymentGateway::Paystack,
                    'gateway_transaction_id' => $payment->gateway_transaction_id ?: ('admin_verified_' . now()->timestamp),
                    'paid_at' => now(),
                    'metadata' => array_merge(is_array($payment->metadata) ? $payment->metadata : [], [
                        'manual_grant' => true,
                        'grant_reason' => $reason,
                        'granted_by_admin_id' => $grantedByAdminId,
                        'granted_at' => now()->toIso8601String(),
                    ]),
                ]);
                $payment = $payment->fresh();
            }

            Log::info('verification.payment.granted_manually', [
                'payment_id' => $payment->id,
                'business_id' => $business->id,
                'granted_by_admin_id' => $grantedByAdminId,
                'reason' => $reason,
            ]);

            $freshBusiness = $business->fresh();

            return [
                'payment' => $payment,
                'business' => $freshBusiness,
                'verification' => $this->verificationService->getVendorVerificationStatus($freshBusiness),
            ];
        });
    }

    /**
     * @return array{payment: Payment, business: BusinessInfo, boost_request: array<string, mixed>|null}
     */
    private function completeAdminVerifiedPendingBoostPayment(
        Payment $payment,
        User $vendor,
        BusinessInfo $business,
        string $reason,
        ?int $grantedByAdminId,
    ): array {
        if ($payment->purpose !== PaymentPurpose::Boost) {
            throw new RuntimeException('Only boost payments can be granted manually.');
        }

        if ($payment->user_id !== $vendor->id) {
            throw new RuntimeException('Payment does not belong to this vendor.');
        }

        return DB::transaction(function () use ($payment, $business, $reason, $grantedByAdminId): array {
            if ($payment->status === PaymentStatus::Pending) {
                $payment->update([
                    'status' => PaymentStatus::Completed,
                    'gateway' => PaymentGateway::Paystack,
                    'gateway_transaction_id' => $payment->gateway_transaction_id ?: ('admin_verified_' . now()->timestamp),
                    'paid_at' => now(),
                    'metadata' => array_merge(is_array($payment->metadata) ? $payment->metadata : [], [
                        'manual_grant' => true,
                        'grant_reason' => $reason,
                        'granted_by_admin_id' => $grantedByAdminId,
                        'granted_at' => now()->toIso8601String(),
                    ]),
                ]);
                $payment = $payment->fresh();
            }

            $boostRequest = $this->boostPurchaseService->markPaidAndQueueForAdmin($payment);
            if ($boostRequest === null) {
                throw new RuntimeException('Boost request was not found for this payment.');
            }

            Log::info('boost.payment.granted_manually', [
                'payment_id' => $payment->id,
                'boost_request_id' => $boostRequest->id,
                'business_id' => $business->id,
                'granted_by_admin_id' => $grantedByAdminId,
                'reason' => $reason,
            ]);

            return [
                'payment' => $payment,
                'business' => $business->fresh(),
                'boost_request' => [
                    'id' => $boostRequest->id,
                    'status' => $boostRequest->status->value,
                ],
            ];
        });
    }

    private function verifyPaystackForSubscriptionPayment(Payment $payment, string $reference): void
    {
        $paystackData = $this->verifyPaystackWithRetry(strtolower(trim($reference)));
        if (strtolower((string) ($paystackData['status'] ?? '')) !== 'success') {
            throw new RuntimeException('Paystack could not verify this payment. Please try again or contact support.');
        }

        $this->assertPaystackAmountMatchesSubscriptionCheckout($payment, $paystackData);
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyPaystackWithRetry(string $reference, int $attempts = 3): array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $this->paystackService->verify($reference);
            } catch (RuntimeException $exception) {
                $lastException = $exception;
                $retryable = stripos($exception->getMessage(), 'could not find this transaction') !== false
                    || stripos($exception->getMessage(), 'reference not found') !== false;

                if (! $retryable || $attempt === $attempts) {
                    throw $exception;
                }

                usleep(800_000);
            }
        }

        throw $lastException ?? new RuntimeException('Paystack verification failed.');
    }

    /**
     * @return array{payment: Payment, business: BusinessInfo}
     */
    private function completeAdminVerifiedPendingPayment(
        Payment $payment,
        User $vendor,
        BusinessInfo $business,
        string $reason,
        ?int $grantedByAdminId,
        ?int $durationDays = null,
    ): array {
        if ($payment->purpose !== PaymentPurpose::Subscription) {
            throw new RuntimeException('Only subscription payments can activate premium.');
        }

        if ($payment->user_id !== $vendor->id) {
            throw new RuntimeException('Payment does not belong to this vendor.');
        }

        return DB::transaction(function () use ($payment, $vendor, $business, $reason, $grantedByAdminId, $durationDays): array {
            if ($payment->status === PaymentStatus::Pending) {
                $payment->update([
                    'status' => PaymentStatus::Completed,
                    'gateway' => PaymentGateway::Paystack,
                    'gateway_transaction_id' => $payment->gateway_transaction_id ?: ('admin_verified_' . now()->timestamp),
                    'paid_at' => now(),
                    'metadata' => array_merge(is_array($payment->metadata) ? $payment->metadata : [], [
                        'manual_grant' => true,
                        'grant_reason' => $reason,
                        'granted_by_admin_id' => $grantedByAdminId,
                        'granted_at' => now()->toIso8601String(),
                    ]),
                ]);
                $payment = $payment->fresh();
            }

            $this->subscriptionService->preparePremiumCheckout($business->fresh(['subscription']));
            $activated = $this->subscriptionService->activatePremiumAfterPayment($payment, $vendor);

            $durationDays = max(1, $durationDays ?? $this->subscriptionService->subscriptionDurationDays());
            if ($durationDays !== $this->subscriptionService->subscriptionDurationDays()) {
                $activated->subscription?->update(['expires_at' => now()->addDays($durationDays)]);
                $activated = $activated->fresh(['subscription']);
            }

            Log::info('subscription.premium.granted_manually', [
                'payment_id' => $payment->id,
                'business_id' => $activated->id,
                'user_id' => $vendor->id,
                'granted_by_admin_id' => $grantedByAdminId,
                'reason' => $reason,
            ]);

            return [
                'payment' => $payment->fresh(),
                'business' => $activated,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $paystackData
     */
    private function assertPaystackAmountForPayment(Payment $payment, array $paystackData): void
    {
        if ($payment->purpose === PaymentPurpose::Subscription) {
            $this->assertPaystackAmountMatchesSubscriptionCheckout($payment, $paystackData);

            return;
        }

        $expectedKobo = (int) round(((float) $payment->amount) * 100);
        $paidKobo = (int) ($paystackData['amount'] ?? 0);

        if ($expectedKobo > 0 && $paidKobo !== $expectedKobo) {
            throw new RuntimeException('Paystack amount does not match the expected payment amount.');
        }
    }

    /**
     * @param  array<string, mixed>  $paystackData
     */
    private function assertPaystackAmountMatchesSubscriptionCheckout(Payment $payment, array $paystackData): void
    {
        $checkout = $this->subscriptionService->checkoutFromSubscriptionPayment($payment);
        $expectedKobo = (int) round(((float) $checkout['total_amount']) * 100);
        $paidKobo = (int) ($paystackData['amount'] ?? 0);

        if ($expectedKobo > 0 && $paidKobo !== $expectedKobo) {
            throw new RuntimeException('Paystack amount does not match the expected checkout total.');
        }
    }
}
