<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\BoostPurchaseRequestStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BoostPurchaseRequestResource;
use App\Http\Resources\Api\V1\LocationResource;
use App\Services\BoostPurchaseService;
use App\Services\BusinessInfoService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VendorBoostController extends Controller
{
    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
        private readonly BoostPurchaseService $boostPurchaseService,
        private readonly SubscriptionService $subscriptionService,
        private readonly PaymentService $paymentService,
    ) {}

    public function catalog(Request $request)
    {
        try {
            $vendor = $request->user('api');
            $business = $this->businessInfoService->findForUser($vendor);

            if ($business === null) {
                return sendResponse(false, 'No business profile found.', null, Response::HTTP_NOT_FOUND);
            }

            $business->load(['location.lgaBoost']);

            if ($business->location === null) {
                return sendResponse(false, 'Set your business location before viewing boost plans.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $campaigns = $this->boostPurchaseService->listForVendor($business);

            return sendResponse(true, 'Boost catalog retrieved successfully.', [
                'location' => new LocationResource($business->location),
                'pending_request' => $this->boostPurchaseService->latestOpenRequestForBusiness($business),
                'is_premium_active' => $this->subscriptionService->hasActivePremium($business),
                'campaigns' => BoostPurchaseRequestResource::collection($campaigns)->resolve(),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function submitRequest(Request $request)
    {
        try {
            $validated = $request->validate([
                'tier_key' => ['required', 'string', 'max:30'],
                'duration_days' => ['required', 'integer', 'in:7,14,30'],
                'location_id' => ['nullable', 'integer', 'exists:locations,id'],
                'renew_type' => ['nullable', 'string', 'in:extend,boost_again'],
                'source_campaign_id' => ['nullable', 'integer', 'exists:boost_purchase_requests,id'],
            ]);

            $vendor = $request->user('api');
            $business = $this->businessInfoService->findForUser($vendor);

            if ($business === null) {
                return sendResponse(false, 'No business profile found.', null, Response::HTTP_NOT_FOUND);
            }

            if (! $this->subscriptionService->hasActivePremium($business)) {
                return sendResponse(false, 'An active premium subscription is required. Add boost during premium checkout or upgrade first.', null, Response::HTTP_FORBIDDEN);
            }

            if (! empty($validated['renew_type'])) {
                return sendResponse(
                    false,
                    'Complete payment on the checkout page to extend or boost again.',
                    null,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $boostRequest = $this->boostPurchaseService->createRequest(
                $vendor,
                $business,
                (string) $validated['tier_key'],
                (int) $validated['duration_days'],
                BoostPurchaseRequestStatus::PendingAdmin,
                null,
                isset($validated['renew_type']) ? (string) $validated['renew_type'] : null,
                isset($validated['source_campaign_id']) ? (int) $validated['source_campaign_id'] : null,
                isset($validated['location_id']) ? (int) $validated['location_id'] : null,
            );

            return sendResponse(true, 'Boost request submitted. An admin will review and confirm activation.', [
                'request' => new BoostPurchaseRequestResource($boostRequest->load(['location', 'businessInfo'])),
                'campaigns' => BoostPurchaseRequestResource::collection(
                    $this->boostPurchaseService->listForVendor($business->fresh()),
                )->resolve(),
            ], Response::HTTP_CREATED);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function initPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'tier_key' => ['required', 'string', 'max:30'],
                'duration_days' => ['required', 'integer', 'in:7,14,30'],
                'location_id' => ['nullable', 'integer', 'exists:locations,id'],
                'renew_type' => ['nullable', 'string', 'in:extend,boost_again'],
                'source_campaign_id' => ['nullable', 'integer', 'exists:boost_purchase_requests,id'],
                'gateway' => ['nullable', 'string', Rule::in(PaymentGateway::values())],
            ]);

            $vendor = $request->user('api');
            $business = $this->businessInfoService->findForUser($vendor);

            if ($business === null) {
                return sendResponse(false, 'No business profile found.', null, Response::HTTP_NOT_FOUND);
            }

            if (! $this->subscriptionService->hasActivePremium($business)) {
                return sendResponse(false, 'An active premium subscription is required.', null, Response::HTTP_FORBIDDEN);
            }

            $result = $this->boostPurchaseService->initBoostPayment(
                $vendor,
                $business,
                (string) $validated['tier_key'],
                (int) $validated['duration_days'],
                isset($validated['renew_type']) ? (string) $validated['renew_type'] : null,
                isset($validated['source_campaign_id']) ? (int) $validated['source_campaign_id'] : null,
                isset($validated['location_id']) ? (int) $validated['location_id'] : null,
                isset($validated['gateway']) ? PaymentGateway::from((string) $validated['gateway']) : null,
            );

            return sendResponse(true, 'Boost payment initialized successfully.', [
                'payment' => $this->paymentService->toArray($result['payment']),
                'request' => new BoostPurchaseRequestResource($result['request']->load(['location', 'businessInfo'])),
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
            $validated = $request->validate([
                'request_id' => ['required', 'integer', 'exists:boost_purchase_requests,id'],
            ]);

            $vendor = $request->user('api');
            $business = $this->businessInfoService->findForUser($vendor);

            if ($business === null) {
                return sendResponse(false, 'No business profile found.', null, Response::HTTP_NOT_FOUND);
            }

            $result = $this->boostPurchaseService->resumeBoostPayment($business, (int) $validated['request_id']);

            return sendResponse(true, 'Boost payment session restored. Complete payment to send the request to admin.', [
                'payment' => $this->paymentService->toArray($result['payment']),
                'request' => new BoostPurchaseRequestResource($result['request']->load(['location', 'businessInfo', 'payment'])),
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
            $validated = $request->validate([
                'payment_id' => ['required', 'integer', 'exists:payments,id'],
                'gateway_transaction_id' => ['required', 'string', 'max:255'],
                'gateway' => ['required', 'string', Rule::in(PaymentGateway::values())],
            ]);

            $vendor = $request->user('api');
            $payment = $this->paymentService->findOwnedPayment(
                (int) $validated['payment_id'],
                $vendor,
                PaymentPurpose::Boost,
            );

            $boostRequest = $this->boostPurchaseService->confirmBoostPayment(
                $payment,
                trim((string) $validated['gateway_transaction_id']),
                PaymentGateway::from((string) $validated['gateway']),
            );

            $business = $boostRequest->businessInfo ?? $this->businessInfoService->findForUser($vendor);

            return sendResponse(true, 'Payment received. An admin will assign your boost shortly.', [
                'payment' => $this->paymentService->toArray($payment->fresh()),
                'request' => new BoostPurchaseRequestResource($boostRequest),
                'campaigns' => $business
                    ? BoostPurchaseRequestResource::collection(
                        $this->boostPurchaseService->listForVendor($business),
                    )->resolve()
                    : [],
            ]);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
