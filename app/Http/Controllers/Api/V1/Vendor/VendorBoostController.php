<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\BoostPurchaseRequestStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BoostPurchaseRequestResource;
use App\Http\Resources\Api\V1\LocationResource;
use App\Models\BusinessInfo;
use App\Models\Location;
use App\Services\BoostPurchaseService;
use App\Services\BusinessInfoService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            $business = $this->businessInfoService->resolveBusinessFromRequest($request);

            $business->load(['location.lgaBoost']);

            if ($business->location === null) {
                return sendResponse(false, 'Set your business location before viewing boost plans.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $campaigns = $this->boostPurchaseService->listForVendor($business);

            $boostLocations = Location::query()
                ->whereHas('lgaBoost', function ($query): void {
                    $query->where('enabled', true);
                })
                ->with('lgaBoost')
                ->orderBy('state_name')
                ->orderBy('lga_name')
                ->get();

            return sendResponse(true, 'Boost catalog retrieved successfully.', [
                'location' => new LocationResource($business->location),
                'boost_locations' => LocationResource::collection($boostLocations)->resolve(),
                'pending_request' => $this->boostPurchaseService->latestOpenRequestForBusiness($business),
                'is_premium_active' => $this->subscriptionService->hasActivePremium($business),
                'is_verified' => $this->subscriptionService->isBusinessVerified($business),
                'can_boost' => $this->subscriptionService->canUseBoost($business),
                'boost_model' => 'dynamic',
                'dynamic' => [
                    'tier_key' => $this->boostPurchaseService->dynamicTierKey(),
                    'tier_label' => config('boost.dynamic.tier_label', 'Dynamic Boost'),
                    'durations' => $this->boostPurchaseService->dynamicDurations(),
                    'budget_min' => (float) config('boost.dynamic.budget_min', 500),
                    'budget_max' => (float) config('boost.dynamic.budget_max', 5000),
                ],
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
            $validated = $this->validateDynamicBoostPayload($request);

            $vendor = $request->user('api');
            $business = $this->businessInfoService->resolveBusinessFromRequest($request);

            if (! $this->subscriptionService->canUseBoost($business)) {
                return sendResponse(
                    false,
                    $this->boostAccessDeniedMessage($business),
                    null,
                    Response::HTTP_FORBIDDEN,
                );
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
                $this->boostPurchaseService->dynamicTierKey(),
                (int) $validated['duration_days'],
                BoostPurchaseRequestStatus::PendingAdmin,
                null,
                isset($validated['renew_type']) ? (string) $validated['renew_type'] : null,
                isset($validated['source_campaign_id']) ? (int) $validated['source_campaign_id'] : null,
                isset($validated['location_id']) ? (int) $validated['location_id'] : null,
                (float) $validated['budget_amount'],
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
        $logContext = [
            'user_id' => $request->user('api')?->id,
            'payload' => $request->only([
                'duration_days',
                'budget_amount',
                'location_id',
                'renew_type',
                'source_campaign_id',
                'gateway',
            ]),
        ];

        try {
            $validated = $this->validateDynamicBoostPayload($request);

            $vendor = $request->user('api');
            $business = $this->businessInfoService->resolveBusinessFromRequest($request);

            if (! $this->subscriptionService->canUseBoost($business)) {
                $message = $this->boostAccessDeniedMessage($business);

                Log::warning('vendor.boost.payment.init.forbidden', array_merge($logContext, [
                    'business_id' => $business->id,
                    'reason' => $message,
                ]));

                return sendResponse(false, $message, null, Response::HTTP_FORBIDDEN);
            }

            Log::info('vendor.boost.payment.init.start', array_merge($logContext, [
                'business_id' => $business->id,
                'validated' => $validated,
            ]));

            $result = $this->boostPurchaseService->initBoostPayment(
                $vendor,
                $business,
                $this->boostPurchaseService->dynamicTierKey(),
                (int) $validated['duration_days'],
                isset($validated['renew_type']) ? (string) $validated['renew_type'] : null,
                isset($validated['source_campaign_id']) ? (int) $validated['source_campaign_id'] : null,
                isset($validated['location_id']) ? (int) $validated['location_id'] : null,
                isset($validated['gateway']) ? PaymentGateway::from((string) $validated['gateway']) : null,
                (float) $validated['budget_amount'],
            );

            Log::info('vendor.boost.payment.init.success', array_merge($logContext, [
                'business_id' => $business->id,
                'payment_id' => $result['payment']->id,
                'request_id' => $result['request']->id,
                'amount' => $result['payment']->amount,
            ]));

            return sendResponse(true, 'Boost payment initialized successfully.', [
                'payment' => $this->paymentService->toArray($result['payment']),
                'request' => new BoostPurchaseRequestResource($result['request']->load(['location', 'businessInfo'])),
            ], Response::HTTP_CREATED);
        } catch (ValidationException $exception) {
            Log::warning('vendor.boost.payment.init.validation_failed', array_merge($logContext, [
                'errors' => $exception->errors(),
            ]));

            throw $exception;
        } catch (RuntimeException $exception) {
            Log::warning('vendor.boost.payment.init.rejected', array_merge($logContext, [
                'reason' => $exception->getMessage(),
            ]));

            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            Log::error('vendor.boost.payment.init.failed', array_merge($logContext, [
                'reason' => $throwable->getMessage(),
            ]));

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
            $business = $this->businessInfoService->resolveBusinessFromRequest($request);

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

    /**
     * @return array<string, mixed>
     */
    private function validateDynamicBoostPayload(Request $request): array
    {
        $durations = $this->boostPurchaseService->dynamicDurations();
        $budgetMin = (float) config('boost.dynamic.budget_min', 500);
        $budgetMax = (float) config('boost.dynamic.budget_max', 5000);

        return $request->validate([
            'duration_days' => ['required', 'integer', Rule::in($durations)],
            'budget_amount' => ['required', 'numeric', 'min:'.$budgetMin, 'max:'.$budgetMax],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'renew_type' => ['nullable', 'string', 'in:extend,boost_again'],
            'source_campaign_id' => ['nullable', 'integer', 'exists:boost_purchase_requests,id'],
            'gateway' => ['nullable', 'string', Rule::in(PaymentGateway::values())],
        ]);
    }

    private function boostAccessDeniedMessage(BusinessInfo $business): string
    {
        if (! $this->subscriptionService->hasActivePremium($business)) {
            return 'An active premium subscription is required. Add boost during premium checkout or upgrade first.';
        }

        if (! $this->subscriptionService->isBusinessVerified($business)) {
            return 'Business verification is required before you can boost your listing.';
        }

        return 'You are not eligible to boost this business.';
    }
}
