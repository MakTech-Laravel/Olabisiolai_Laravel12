<?php

namespace App\Services;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\BusinessInfo;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    public function __construct(
        private readonly PricingPackageService $pricingPackageService,
        private readonly RealtimeNotificationService $realtimeNotifications,
        private readonly PaymentGatewayResolver $gatewayResolver,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function findPackage(PaymentPurpose $purpose, string $packageId): ?array
    {
        return $this->pricingPackageService->findPackage($purpose, $packageId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function subscriptionPackages(): array
    {
        return $this->pricingPackageService->subscriptionPackages();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function verificationPackages(): array
    {
        return $this->pricingPackageService->verificationPackages();
    }

    /**
     * Standalone boost checkout (extend, boost again, or paid boost purchase).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function initBoostPayment(
        User $user,
        BusinessInfo $business,
        float $amount,
        array $metadata = [],
        ?string $packageId = null,
        ?PaymentGateway $gateway = null,
    ): Payment {
        if ($amount <= 0) {
            throw new RuntimeException('Boost amount is invalid.');
        }

        $tierLabel = is_array($metadata) ? (string) ($metadata['boost_tier_label'] ?? 'Boost campaign') : 'Boost campaign';

        return Payment::query()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Boost,
            'package_id' => $packageId ?? (string) ($metadata['boost_tier_key'] ?? 'boost_purchase'),
            'amount' => $amount,
            'currency' => $this->pricingPackageService->boostCurrency(),
            'tx_ref' => $this->generateTxRef(PaymentPurpose::Boost, $user->id),
            'gateway' => ($gateway ?? PaymentGateway::Flutterwave),
            'status' => PaymentStatus::Pending,
            'metadata' => array_merge([
                'package_title' => $tierLabel,
                'line_item' => 'boosting',
                'reference_type' => PaymentPurpose::Boost->value,
            ], $metadata),
        ]);
    }

    public function initPayment(
        User $user,
        BusinessInfo $business,
        PaymentPurpose $purpose,
        string $packageId,
        float $additionalAmount = 0,
        ?array $metadata = null,
        ?PaymentGateway $gateway = null,
    ): Payment {
        $package = $this->findPackage($purpose, $packageId);

        if ($package === null) {
            throw new RuntimeException('Invalid package selected.');
        }

        $amount = (float) ($package['amount'] ?? 0) + max(0, $additionalAmount);

        if ($amount <= 0) {
            throw new RuntimeException('Package amount is invalid.');
        }

        $baseMetadata = [
            'package_title' => $package['title'] ?? $packageId,
            'base_amount' => (float) ($package['amount'] ?? 0),
            'additional_amount' => max(0, $additionalAmount),
            'line_item' => $purpose->value,
        ];

        return Payment::query()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'purpose' => $purpose,
            'package_id' => $packageId,
            'amount' => $amount,
            'currency' => match ($purpose) {
                PaymentPurpose::Subscription => $this->pricingPackageService->subscriptionCurrency(),
                PaymentPurpose::Verification => $this->pricingPackageService->verificationCurrency(),
                PaymentPurpose::Boost => $this->pricingPackageService->boostCurrency(),
            },
            'tx_ref' => $this->generateTxRef($purpose, $user->id),
            'gateway' => ($gateway ?? PaymentGateway::Flutterwave),
            'status' => PaymentStatus::Pending,
            'metadata' => array_merge($baseMetadata, $metadata ?? []),
        ]);
    }

    public function confirmPayment(Payment $payment, string $gatewayTransactionId, PaymentGateway $gateway): Payment
    {
        if ($payment->status === PaymentStatus::Completed) {
            return $payment;
        }

        if ($payment->status === PaymentStatus::Failed) {
            throw new RuntimeException('This payment has failed and cannot be confirmed.');
        }

        $this->assertGatewayTransactionAvailable($gatewayTransactionId, $payment);

        $payment->update([
            'status' => PaymentStatus::Completed,
            'gateway' => $gateway,
            'gateway_transaction_id' => $gatewayTransactionId,
            'paid_at' => now(),
        ]);

        $payment = $payment->fresh(['user']);

        if ($payment->user !== null) {
            $purposeLabel = $payment->purpose->value;
            $packageTitle = is_array($payment->metadata)
                ? ($payment->metadata['package_title'] ?? $purposeLabel)
                : $purposeLabel;

            $this->realtimeNotifications->paymentCompleted(
                user: $payment->user,
                purposeLabel: (string) $packageTitle,
                amount: (float) $payment->amount,
                currency: (string) $payment->currency,
            );
        }

        return $payment;
    }

    /**
     * Ensure a gateway reference has not already been used by another completed checkout.
     */
    public function assertGatewayTransactionAvailable(string $gatewayTransactionId, Payment $payment): void
    {
        $gatewayTransactionId = strtolower(trim($gatewayTransactionId));
        if ($gatewayTransactionId === '') {
            throw new RuntimeException('Payment reference is required.');
        }

        if (
            $payment->status === PaymentStatus::Completed
            && strtolower((string) ($payment->gateway_transaction_id ?? '')) === $gatewayTransactionId
        ) {
            return;
        }

        $allowedPaymentIds = $this->relatedBundledPaymentIds($payment);

        $conflict = Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->whereNotIn('id', $allowedPaymentIds)
            ->where(function ($query) use ($gatewayTransactionId): void {
                $query->whereRaw('LOWER(gateway_transaction_id) = ?', [$gatewayTransactionId])
                    ->orWhereRaw('LOWER(tx_ref) = ?', [$gatewayTransactionId]);
            })
            ->first();

        if ($conflict !== null) {
            throw new RuntimeException('This payment reference has already been used.');
        }
    }

    /**
     * @return list<int>
     */
    public function relatedBundledPaymentIds(Payment $payment): array
    {
        $ids = [$payment->id];
        $meta = is_array($payment->metadata) ? $payment->metadata : [];

        $boostPaymentId = (int) ($meta['boost_payment_id'] ?? 0);
        if ($boostPaymentId > 0) {
            $ids[] = $boostPaymentId;
        }

        $subscriptionPaymentId = (int) ($meta['subscription_payment_id'] ?? 0);
        if ($subscriptionPaymentId > 0) {
            $ids[] = $subscriptionPaymentId;
        }

        $checkoutGroupId = $meta['checkout_group_id'] ?? null;
        if (is_string($checkoutGroupId) && $checkoutGroupId !== '') {
            $groupMemberIds = Payment::query()
                ->where('user_id', $payment->user_id)
                ->where('metadata->checkout_group_id', $checkoutGroupId)
                ->pluck('id')
                ->all();

            $ids = array_merge($ids, $groupMemberIds);
        }

        return array_values(array_unique($ids));
    }

    public function markFailed(Payment $payment): Payment
    {
        $payment->update(['status' => PaymentStatus::Failed]);

        return $payment->fresh();
    }

    public function consumePayment(Payment $payment): Payment
    {
        if (! $payment->isConsumable()) {
            throw new RuntimeException('Payment is not available for use. Complete payment first.');
        }

        $payment->update(['is_consumed' => true]);

        return $payment->fresh();
    }

    public function findOwnedPayment(int $paymentId, User $user, PaymentPurpose $purpose): Payment
    {
        $payment = Payment::query()
            ->whereKey($paymentId)
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->first();

        if ($payment === null) {
            throw new RuntimeException('Payment not found.');
        }

        return $payment;
    }

    public function findConsumablePayment(int $paymentId, User $user, PaymentPurpose $purpose): Payment
    {
        $payment = $this->findOwnedPayment($paymentId, $user, $purpose);

        if (! $payment->isConsumable()) {
            throw new RuntimeException('Payment must be completed before submitting your request.');
        }

        return $payment;
    }

    private function generateTxRef(PaymentPurpose $purpose, int $userId): string
    {
        return sprintf('%s_%s_%s', $purpose->value, $userId, Str::lower(Str::random(12)));
    }

    public function refreshTransactionReference(Payment $payment): Payment
    {
        $payment->update([
            'tx_ref' => $this->generateTxRef($payment->purpose, (int) $payment->user_id),
        ]);

        return $payment->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Payment $payment): array
    {
        $gateway = $this->gatewayResolver->resolveForDisplay($payment);

        return [
            'id' => $payment->id,
            'purpose' => $payment->purpose->value,
            'reference_type' => $payment->purpose->value,
            'purpose_label' => $payment->purpose->label(),
            'package_id' => $payment->package_id,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'tx_ref' => $payment->tx_ref,
            'gateway' => $gateway?->value,
            'status' => $payment->status->value,
            'is_consumed' => $payment->is_consumed,
            'paid_at' => $payment->paid_at ? humanDateTime($payment->paid_at) : null,
            'metadata' => $payment->metadata,
        ];
    }

    /**
     * Confirm a bundled checkout (e.g. premium + boost) using one gateway transaction.
     *
     * @return list<Payment>
     */
    public function confirmBundledPayments(Payment $primaryPayment, string $gatewayTransactionId, PaymentGateway $gateway): array
    {
        $confirmed = [];

        if ($primaryPayment->status === PaymentStatus::Pending) {
            $confirmed[] = $this->confirmPayment($primaryPayment, $gatewayTransactionId, $gateway);
        } else {
            $confirmed[] = $primaryPayment;
        }

        $meta = is_array($primaryPayment->metadata) ? $primaryPayment->metadata : [];
        $boostPaymentId = isset($meta['boost_payment_id']) ? (int) $meta['boost_payment_id'] : 0;

        if ($boostPaymentId > 0) {
            $boostPayment = Payment::query()->find($boostPaymentId);

            if (
                $boostPayment !== null
                && $boostPayment->purpose === PaymentPurpose::Boost
                && $boostPayment->user_id === $primaryPayment->user_id
                && $boostPayment->status === PaymentStatus::Pending
            ) {
                $confirmed[] = $this->confirmPayment($boostPayment, $gatewayTransactionId, $gateway);
            }
        }

        return $confirmed;
    }

    /**
     * @return array{wallet_applied: float, gateway_amount: float, original_total: float}
     */
    public function walletSplitFromPayment(Payment $payment): array
    {
        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $originalTotal = (float) ($meta['original_total'] ?? $payment->amount);
        $walletApplied = (float) ($meta['wallet_applied'] ?? 0);
        $gatewayAmount = isset($meta['gateway_amount'])
            ? (float) $meta['gateway_amount']
            : max(0, $originalTotal - $walletApplied);

        return [
            'wallet_applied' => $walletApplied,
            'gateway_amount' => $gatewayAmount,
            'original_total' => $originalTotal,
        ];
    }

    public function hasWalletGatewaySplit(Payment $payment): bool
    {
        if ($payment->status !== PaymentStatus::Completed) {
            return false;
        }

        $split = $this->walletSplitFromPayment($payment);
        if ($split['wallet_applied'] <= 0 || $split['gateway_amount'] <= 0) {
            return false;
        }

        $gateway = $this->gatewayResolver->resolveForDisplay($payment);

        return $gateway !== null && $gateway !== PaymentGateway::Wallet;
    }

    /**
     * Expand a completed split checkout into separate wallet + gateway list rows.
     *
     * @return list<array<string, mixed>>
     */
    public function expandVendorListItems(Payment $payment): array
    {
        if (! $this->hasWalletGatewaySplit($payment)) {
            $item = $this->toVendorListItem($payment);
            $item['list_key'] = (string) $payment->id;

            return [$item];
        }

        $split = $this->walletSplitFromPayment($payment);
        $base = $this->toVendorListItem($payment);
        $gateway = $this->gatewayResolver->resolveForDisplay($payment);
        $description = (string) ($base['description'] ?? $payment->purpose->label());

        return [
            array_merge($base, [
                'amount' => $split['wallet_applied'],
                'gateway' => PaymentGateway::Wallet->value,
                'description' => $description.' · Wallet credit',
                'list_key' => $payment->id.'-wallet',
                'split_part' => 'wallet',
            ]),
            array_merge($base, [
                'amount' => $split['gateway_amount'],
                'gateway' => $gateway?->value,
                'description' => $description.' · Card payment',
                'list_key' => $payment->id.'-gateway',
                'split_part' => 'gateway',
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function expandVendorCsvRows(Payment $payment): array
    {
        return array_map(
            fn (array $item): array => $this->vendorCsvRowFromListItem($payment, $item),
            $this->expandVendorListItems($payment),
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    public function vendorCsvRowFromListItem(Payment $payment, array $item): array
    {
        $metadataJson = is_array($payment->metadata)
            ? (string) json_encode($payment->metadata, JSON_UNESCAPED_UNICODE)
            : '';

        return [
            (string) $payment->id,
            $payment->purpose->value,
            $payment->purpose->label(),
            (string) ($item['description'] ?? ''),
            (string) ($item['amount'] ?? $payment->amount),
            $payment->currency,
            $payment->status->value,
            (string) ($item['tx_ref'] ?? $payment->tx_ref),
            (string) ($item['gateway'] ?? ''),
            $payment->paid_at ? humanDateTime($payment->paid_at) : '',
            $payment->created_at?->toIso8601String() ?? '',
            (string) ($payment->gateway_transaction_id ?? ''),
            (string) ($payment->package_id ?? ''),
            $payment->is_consumed ? 'yes' : 'no',
            $metadataJson,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toVendorListItem(Payment $payment): array
    {
        $gateway = $this->gatewayResolver->resolveForDisplay($payment);
        $meta = $payment->metadata ?? [];
        $title = is_array($meta) ? (string) ($meta['package_title'] ?? $payment->package_id ?? '') : '';

        return [
            'id' => $payment->id,
            'purpose' => $payment->purpose->value,
            'reference_type' => $payment->purpose->value,
            'purpose_label' => $payment->purpose->label(),
            'description' => $title !== '' ? $title : $payment->purpose->label(),
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status->value,
            'tx_ref' => $payment->tx_ref,
            'gateway' => $gateway?->value,
            'paid_at' => $payment->paid_at ? humanDateTime($payment->paid_at) : null,
            'paid_at_iso' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toVendorDetail(Payment $payment): array
    {
        return array_merge($this->toVendorListItem($payment), [
            'gateway_transaction_id' => $payment->gateway_transaction_id,
            'is_consumed' => $payment->is_consumed,
            'package_id' => $payment->package_id,
            'metadata' => $payment->metadata,
        ]);
    }

    /**
     * @return list<string>
     */
    public function vendorPaymentExportHeaders(): array
    {
        return [
            'ID',
            'Type code',
            'Type',
            'Description',
            'Amount',
            'Currency',
            'Status',
            'TX reference',
            'Gateway',
            'Paid at',
            'Created at',
            'Gateway transaction ID',
            'Package ID',
            'Consumed',
            'Metadata JSON',
        ];
    }

    /**
     * @return list<string>
     */
    public function toVendorCsvRow(Payment $payment): array
    {
        $meta = $payment->metadata ?? [];
        $title = is_array($meta) ? (string) ($meta['package_title'] ?? $payment->package_id ?? '') : '';
        $description = $title !== '' ? $title : $payment->purpose->label();
        $metadataJson = is_array($payment->metadata) ? (string) json_encode($payment->metadata, JSON_UNESCAPED_UNICODE) : '';

        $gateway = $this->gatewayResolver->resolveForDisplay($payment);

        return [
            (string) $payment->id,
            $payment->purpose->value,
            $payment->purpose->label(),
            $description,
            (string) $payment->amount,
            $payment->currency,
            $payment->status->value,
            $payment->tx_ref,
            $gateway?->value ?? '',
            $payment->paid_at ? humanDateTime($payment->paid_at) : '',
            $payment->created_at?->toIso8601String() ?? '',
            (string) ($payment->gateway_transaction_id ?? ''),
            (string) ($payment->package_id ?? ''),
            $payment->is_consumed ? 'yes' : 'no',
            $metadataJson,
        ];
    }
}
