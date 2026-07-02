<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BusinessInfoResource;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Models\BusinessInfo;
use App\Models\Payment;
use App\Services\AdminPaymentService;
use App\Services\PaymentReconciliationService;
use App\Services\PaymentService;
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
        private readonly PaymentService $paymentService,
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
            ]);

            $business = BusinessInfo::query()
                ->with(['subscription', 'user'])
                ->findOrFail((int) $validated['business_id']);

            $result = $this->paymentReconciliation->grantPremiumManually(
                $business,
                (string) $validated['reason'],
                $request->user('admin_api')?->id,
                isset($validated['duration_days']) ? (int) $validated['duration_days'] : null,
                isset($validated['paystack_reference']) ? (string) $validated['paystack_reference'] : null,
            );

            $activatedBusiness = $result['business'];
            $activatedBusiness->load(['category:id,name,subcategories', 'location:id,lga_name,state_name,city_name,country_name']);

            return sendResponse(true, 'Premium subscription activated successfully.', [
                'payment' => $this->paymentService->toArray($result['payment']),
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
                $business->load(['category:id,name,subcategories', 'location:id,lga_name,state_name,city_name,country_name']);
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
                $business->load(['category:id,name,subcategories', 'location:id,lga_name,state_name,city_name,country_name']);
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
}
