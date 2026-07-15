<?php

namespace App\Services;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WalletService
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'currency' => config('subscription.currency', 'NGN')],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function walletPayload(User $user, int $transactionLimit = 20): array
    {
        $wallet = $this->getOrCreateWallet($user);
        $wallet->load([
            'transactions' => fn ($query) => $query->limit($transactionLimit),
        ]);

        return [
            'balance' => (float) $wallet->balance,
            'currency' => $wallet->currency,
            'transactions' => $wallet->transactions->map(fn (WalletTransaction $tx): array => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => (float) $tx->amount,
                'balance_after' => (float) $tx->balance_after,
                'description' => $tx->description,
                'reference' => $tx->reference,
                'created_at' => $tx->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    public function credit(
        User $user,
        float $amount,
        string $description,
        ?string $reference = null,
        ?array $metadata = null,
    ): Wallet {
        if ($amount <= 0) {
            throw new RuntimeException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $description, $reference, $metadata): Wallet {
            $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->first()
                ?? $this->getOrCreateWallet($user)->refresh();

            $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $nextBalance = (float) $wallet->balance + $amount;
            $wallet->update(['balance' => $nextBalance]);

            WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $nextBalance,
                'description' => $description,
                'reference' => $reference,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            return $wallet->refresh();
        });
    }

    public function debit(User $user, float $amount, string $description, ?string $reference = null): Wallet
    {
        if ($amount <= 0) {
            throw new RuntimeException('Debit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $description, $reference): Wallet {
            $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->first()
                ?? $this->getOrCreateWallet($user)->refresh();

            $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ((float) $wallet->balance < $amount) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $nextBalance = (float) $wallet->balance - $amount;
            $wallet->update(['balance' => $nextBalance]);

            WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $nextBalance,
                'description' => $description,
                'reference' => $reference,
                'created_at' => now(),
            ]);

            return $wallet->refresh();
        });
    }

    public function initTopUp(User $user, float $amount, ?PaymentGateway $gateway = null): array
    {
        if ($amount < 500) {
            throw new RuntimeException('Minimum top-up is ₦500.');
        }

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'business_info_id' => null,
            'purpose' => PaymentPurpose::WalletTopUp,
            'package_id' => 'wallet_topup',
            'amount' => $amount,
            'currency' => config('subscription.currency', 'NGN'),
            'tx_ref' => 'wallet_'.$user->id.'_'.Str::lower(Str::random(16)),
            'gateway' => $gateway ?? PaymentGateway::Paystack,
            'status' => PaymentStatus::Pending,
            'metadata' => [
                'package_title' => 'Wallet top-up',
                'line_item' => 'wallet_topup',
                'reference_type' => PaymentPurpose::WalletTopUp->value,
            ],
        ]);

        return [
            'payment_id' => $payment->id,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'tx_ref' => $payment->tx_ref,
            'gateway' => $payment->gateway->value,
        ];
    }

    public function confirmTopUp(Payment $payment, string $gatewayTransactionId, PaymentGateway $gateway): Wallet
    {
        if ($payment->purpose !== PaymentPurpose::WalletTopUp) {
            throw new RuntimeException('Invalid wallet top-up payment.');
        }

        $user = $payment->user;
        if ($user === null) {
            throw new RuntimeException('Wallet top-up user not found.');
        }

        if ($payment->isCompleted() && $payment->is_consumed) {
            return $this->getOrCreateWallet($user);
        }

        $this->paymentService->assertGatewayTransactionAvailable($gatewayTransactionId, $payment);

        if ($payment->status === PaymentStatus::Pending) {
            $this->paymentService->confirmPayment($payment, $gatewayTransactionId, $gateway);
            $payment = $payment->fresh();
        }

        if ($payment->is_consumed) {
            return $this->getOrCreateWallet($user);
        }

        $payment->update(['is_consumed' => true]);

        return $this->credit(
            $user,
            (float) $payment->amount,
            'Wallet top-up',
            $payment->tx_ref,
            ['payment_id' => $payment->id],
        );
    }

    /**
     * @return array{wallet_applied: float, gateway_amount: float, wallet_balance: float, original_total: float}
     */
    public function computeApplication(User $user, float $total): array
    {
        $total = max(0, round($total, 2));
        $wallet = $this->getOrCreateWallet($user);
        $balance = round((float) $wallet->balance, 2);
        $walletApplied = round(min($balance, $total), 2);
        $gatewayAmount = round(max(0, $total - $walletApplied), 2);

        return [
            'wallet_applied' => $walletApplied,
            'gateway_amount' => $gatewayAmount,
            'wallet_balance' => $balance,
            'original_total' => $total,
        ];
    }

    /**
     * @return array{wallet_applied: float, gateway_amount: float, wallet_balance: float, original_total: float}
     */
    public function attachApplicationToPayment(Payment $payment, User $user, float $total): array
    {
        $application = $this->computeApplication($user, $total);

        if ($application['wallet_applied'] <= 0) {
            return $application;
        }

        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $previousGatewayAmount = isset($meta['gateway_amount']) ? (float) $meta['gateway_amount'] : null;
        $previousWalletApplied = (float) ($meta['wallet_applied'] ?? 0);
        $gatewayAmountChanged = $previousGatewayAmount === null
            || abs($previousGatewayAmount - $application['gateway_amount']) > 0.009;
        $walletAmountChanged = abs($previousWalletApplied - $application['wallet_applied']) > 0.009;

        if (
            $application['gateway_amount'] > 0
            && ($gatewayAmountChanged || $walletAmountChanged || $previousGatewayAmount === null)
        ) {
            $payment = app(PaymentService::class)->refreshTransactionReference($payment);
            $meta = is_array($payment->metadata) ? $payment->metadata : [];
        }

        $payment->update([
            'metadata' => array_merge($meta, [
                'wallet_applied' => $application['wallet_applied'],
                'gateway_amount' => $application['gateway_amount'],
                'original_total' => $application['original_total'],
                'wallet_debited' => false,
            ]),
        ]);

        return $application;
    }

    /**
     * @return array{wallet_applied: float, gateway_amount: float, original_total: float}
     */
    public function readApplicationFromPayment(Payment $payment, ?float $fallbackTotal = null): array
    {
        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $originalTotal = isset($meta['original_total'])
            ? (float) $meta['original_total']
            : ($fallbackTotal ?? (float) $payment->amount);

        return [
            'wallet_applied' => (float) ($meta['wallet_applied'] ?? 0),
            'gateway_amount' => isset($meta['gateway_amount'])
                ? (float) $meta['gateway_amount']
                : $originalTotal,
            'original_total' => $originalTotal,
        ];
    }

    public function gatewayAmountFor(Payment $payment, ?float $fallbackTotal = null): float
    {
        return $this->readApplicationFromPayment($payment, $fallbackTotal)['gateway_amount'];
    }

    public function settleApplication(User $user, Payment $payment, string $description): float
    {
        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $walletApplied = (float) ($meta['wallet_applied'] ?? 0);

        if ($walletApplied <= 0 || ($meta['wallet_debited'] ?? false)) {
            return $walletApplied;
        }

        $this->debit($user, $walletApplied, $description, $payment->tx_ref);

        $payment->update([
            'metadata' => array_merge($meta, ['wallet_debited' => true]),
        ]);

        return $walletApplied;
    }

    /**
     * Debit wallet for a purchase when balance covers the full amount.
     */
    public function tryPayInFull(User $user, float $amount, string $description, ?string $reference = null): bool
    {
        $wallet = $this->getOrCreateWallet($user);
        if ((float) $wallet->balance < $amount) {
            return false;
        }

        $this->debit($user, $amount, $description, $reference);

        return true;
    }

    /**
     * Pay a pending checkout entirely from the user's wallet and mark it completed,
     * reusing the same completion path (status, notification) as a gateway confirm.
     */
    public function payForPendingPayment(User $user, Payment $payment, string $description): Payment
    {
        if ($payment->user_id !== $user->id) {
            throw new RuntimeException('Payment does not belong to this user.');
        }

        if ($payment->status !== PaymentStatus::Pending) {
            throw new RuntimeException('This payment is not awaiting payment.');
        }

        $this->debit($user, (float) $payment->amount, $description, $payment->tx_ref);

        return $this->paymentService->confirmPayment(
            $payment,
            'wallet_'.$payment->tx_ref,
            PaymentGateway::Wallet,
        );
    }
}
