<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\PaymentPurpose;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BusinessInfoResource;
use App\Services\BusinessInfoService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VendorSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly BusinessInfoService $businessInfoService,
        private readonly PaymentService $paymentService,
    ) {}

    public function packages()
    {
        return sendResponse(true, 'Subscription packages retrieved successfully.', [
            'currency' => config('subscription.currency', 'NGN'),
            'packages' => $this->subscriptionService->packages(),
        ]);
    }

    public function status(Request $request)
    {
        try {
            $vendor = $request->user('api');
            $business = $this->businessInfoService->findForUser($vendor);

            if ($business === null) {
                return sendResponse(false, 'No business profile found.', null, Response::HTTP_NOT_FOUND);
            }

            return sendResponse(true, 'Subscription status retrieved successfully.', [
                'subscription' => $this->subscriptionService->subscriptionPayload($business),
                'business' => new BusinessInfoResource($business),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function initPayment(Request $request)
    {
        try {
            $vendor = $request->user('api');
            $business = $this->businessInfoService->findForUser($vendor);

            if ($business === null) {
                return sendResponse(false, 'No business profile found. Please create a business profile first.', null, Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'boost_tier_key' => ['nullable', 'string', 'max:30'],
                'boost_duration_days' => ['nullable', 'integer', 'in:7,14,30'],
            ]);

            $boostTierKey = isset($validated['boost_tier_key']) ? (string) $validated['boost_tier_key'] : null;
            $boostDurationDays = isset($validated['boost_duration_days'])
                ? (int) $validated['boost_duration_days']
                : null;

            if (($boostTierKey === null) xor ($boostDurationDays === null)) {
                return sendResponse(false, 'Provide both boost plan and duration, or omit boost entirely.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $checkout = $this->subscriptionService->initPremiumPayment(
                $vendor,
                $business,
                $boostTierKey,
                $boostDurationDays,
            );

            $subscriptionPayment = $checkout['subscription_payment'];
            $boostPayment = $checkout['boost_payment'];

            return sendResponse(true, 'Premium subscription payment initialized successfully.', [
                'payment' => $this->paymentService->toArray($subscriptionPayment),
                'payments' => [
                    'subscription' => $this->paymentService->toArray($subscriptionPayment),
                    'boost' => $boostPayment !== null
                        ? $this->paymentService->toArray($boostPayment)
                        : null,
                ],
                'total_amount' => $checkout['total_amount'],
                'currency' => $checkout['currency'],
            ], Response::HTTP_CREATED);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resumePayment(Request $request)
    {
        try {
            $vendor = $request->user('api');
            $business = $this->businessInfoService->findForUser($vendor);

            if ($business === null) {
                return sendResponse(false, 'No business profile found. Please create a business profile first.', null, Response::HTTP_NOT_FOUND);
            }

            $payment = $this->subscriptionService->findResumableSubscriptionPayment($vendor, $business);

            if ($payment === null) {
                return sendResponse(false, 'No pending premium payment found.', null, Response::HTTP_NOT_FOUND);
            }

            $checkout = $this->subscriptionService->checkoutFromSubscriptionPayment($payment);
            $subscriptionPayment = $checkout['subscription_payment'];
            $boostPayment = $checkout['boost_payment'];

            return sendResponse(true, 'Pending premium payment resumed successfully.', [
                'payment' => $this->paymentService->toArray($subscriptionPayment),
                'payments' => [
                    'subscription' => $this->paymentService->toArray($subscriptionPayment),
                    'boost' => $boostPayment !== null
                        ? $this->paymentService->toArray($boostPayment)
                        : null,
                ],
                'total_amount' => $checkout['total_amount'],
                'currency' => $checkout['currency'],
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function confirmPayment(Request $request)
    {
        try {
            $vendor = $request->user('api');

            $validated = $request->validate([
                'payment_id' => [
                    'required',
                    'integer',
                    Rule::exists('payments', 'id')->where(function ($query) use ($vendor): void {
                        $query->where('user_id', $vendor->id)
                            ->where('purpose', PaymentPurpose::Subscription->value);
                    }),
                ],
                'gateway_transaction_id' => ['required', 'string', 'max:255'],
            ], [
                'payment_id.exists' => 'Checkout expired. Tap Pay again to start a new premium payment.',
            ]);

            $payment = $this->paymentService->findOwnedPayment(
                (int) $validated['payment_id'],
                $vendor,
                PaymentPurpose::Subscription,
            );

            $gatewayTransactionId = trim((string) $validated['gateway_transaction_id']);

            $business = $this->businessInfoService->findForUser($vendor);

            if ($business !== null && $this->subscriptionService->hasActivePremium($business)) {
                return sendResponse(true, 'Premium subscription is already active.', [
                    'payment' => $this->paymentService->toArray($payment->fresh()),
                    'subscription' => $this->subscriptionService->subscriptionPayload($business),
                    'business' => new BusinessInfoResource($business),
                ]);
            }

            if ($payment->status->value === 'pending') {
                $this->paymentService->confirmBundledPayments($payment, $gatewayTransactionId);
                $payment = $payment->fresh();
            } elseif ($payment->gateway_transaction_id === null && $gatewayTransactionId !== '') {
                $payment->update(['gateway_transaction_id' => $gatewayTransactionId]);
                $payment = $payment->fresh();
            }

            $business = $this->subscriptionService->activatePremiumAfterPayment($payment, $vendor);
            $business->load(['category:id,name,subcategories', 'location:id,lga_name,state_name,city_name,country_name']);

            return sendResponse(true, 'Premium subscription activated successfully. Apply for verification separately to earn your trust badge.', [
                'payment' => $this->paymentService->toArray($payment->fresh()),
                'subscription' => $this->subscriptionService->subscriptionPayload($business),
                'business' => new BusinessInfoResource($business),
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
