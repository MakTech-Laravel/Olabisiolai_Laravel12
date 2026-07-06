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
            'tx_ref' => 'wallet_'.$user->id.'_'.now()->timestamp.'_'.random_int(1000, 9999),
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
