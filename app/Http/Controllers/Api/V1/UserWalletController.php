<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserWalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly PaymentService $paymentService,
    ) {}

    public function show(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user('api');
        if ($user === null) {
            return sendResponse(false, 'Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        return sendResponse(true, 'Wallet retrieved successfully.', [
            'wallet' => $this->walletService->walletPayload($user),
        ]);
    }

    public function initTopUp(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user('api');
        if ($user === null) {
            return sendResponse(false, 'Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:500', 'max:500000'],
            'gateway' => ['nullable', 'string', Rule::in(PaymentGateway::values())],
        ]);

        try {
            $gateway = isset($validated['gateway'])
                ? PaymentGateway::from((string) $validated['gateway'])
                : null;

            $checkout = $this->walletService->initTopUp($user, (float) $validated['amount'], $gateway);

            return sendResponse(true, 'Wallet top-up initialized successfully.', [
                'checkout' => $checkout,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, $throwable->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function confirmTopUp(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user('api');
        if ($user === null) {
            return sendResponse(false, 'Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'payment_id' => ['required', 'integer', 'min:1'],
            'gateway_transaction_id' => ['required', 'string', 'max:255'],
            'gateway' => ['required', 'string', Rule::in(PaymentGateway::values())],
        ]);

        try {
            $payment = $this->paymentService->findOwnedPayment(
                (int) $validated['payment_id'],
                $user,
                PaymentPurpose::WalletTopUp,
            );

            $this->walletService->confirmTopUp(
                $payment,
                trim((string) $validated['gateway_transaction_id']),
                PaymentGateway::from((string) $validated['gateway']),
            );

            return sendResponse(true, 'Wallet topped up successfully.', [
                'wallet' => $this->walletService->walletPayload($user),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, $throwable->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
