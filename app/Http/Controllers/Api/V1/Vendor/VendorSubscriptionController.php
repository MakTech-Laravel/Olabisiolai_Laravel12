<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BusinessInfoResource;
use App\Services\BusinessInfoService;
use App\Services\PaymentReconciliationService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VendorSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly BusinessInfoService $businessInfoService,
        private readonly PaymentService $paymentService,
        private readonly PaymentReconciliationService $paymentReconciliation,
    ) {}

    #[OA\Get(
        path: '/v1/user/subscription/packages',
        summary: 'List premium subscription packages',
        tags: ['Billing'],
        security: [['passport' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Packages retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'currency', type: 'string', example: 'NGN'),
                        new OA\Property(property: 'packages', type: 'array', items: new OA\Items(type: 'object')),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function packages()
    {
        return sendResponse(true, 'Subscription packages retrieved successfully.', [
            'currency' => config('subscription.currency', 'NGN'),
            'packages' => $this->subscriptionService->packages(),
        ]);
    }

    #[OA\Get(
        path: '/v1/user/subscription/status',
        summary: 'Get the vendor\'s business subscription status',
        tags: ['Billing'],
        security: [['passport' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription status retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'subscription', ref: '#/components/schemas/Subscription'),
                        new OA\Property(property: 'business', type: 'object'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error (e.g. no business selected)', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function status(Request $request)
    {
        try {
            $business = $this->businessInfoService->resolveBusinessFromRequest($request);

            return sendResponse(true, 'Subscription status retrieved successfully.', [
                'subscription' => $this->subscriptionService->subscriptionPayload($business),
                'business' => new BusinessInfoResource($business),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/user/subscription/payment/init',
        summary: 'Initialize a premium subscription payment (optionally bundled with a boost)',
        description: 'Starts a gateway checkout for the premium plan, or pays instantly from the vendor\'s wallet '
            .'when use_wallet is true. The client confirms the gateway transaction via POST /v1/user/subscription/payment/confirm.',
        tags: ['Billing'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'gateway', type: 'string', enum: ['flutterwave', 'paystack'], nullable: true),
                new OA\Property(property: 'business_id', type: 'integer', nullable: true),
                new OA\Property(property: 'boost_tier_key', type: 'string', maxLength: 30, nullable: true, description: 'Provide together with boost_duration_days, or omit both.'),
                new OA\Property(property: 'boost_duration_days', type: 'integer', minimum: 1, maximum: 30, nullable: true),
                new OA\Property(property: 'boost_budget_amount', type: 'number', format: 'float', minimum: 500, maximum: 5000, nullable: true),
                new OA\Property(property: 'use_wallet', type: 'boolean', nullable: true, description: 'Pay from wallet balance instead of a gateway.'),
            ]),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Payment initialized (or paid instantly from wallet)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'payment', ref: '#/components/schemas/Payment', nullable: true),
                        new OA\Property(property: 'payments', properties: [
                            new OA\Property(property: 'subscription', ref: '#/components/schemas/Payment'),
                            new OA\Property(property: 'boost', ref: '#/components/schemas/Payment', nullable: true),
                        ], type: 'object', nullable: true),
                        new OA\Property(property: 'total_amount', type: 'number', format: 'float', nullable: true),
                        new OA\Property(property: 'currency', type: 'string', nullable: true),
                        new OA\Property(property: 'business', type: 'object', nullable: true),
                        new OA\Property(property: 'wallet_balance', type: 'number', format: 'float', nullable: true),
                        new OA\Property(property: 'paid_from_wallet', type: 'boolean', nullable: true),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Invalid boost combination, business error, or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function initPayment(Request $request)
    {
        try {
            $vendor = $request->user('api');
            $business = $this->businessInfoService->resolveBusinessFromRequest($request);

            $validated = $request->validate([
                'gateway' => ['nullable', 'string', Rule::in(PaymentGateway::values())],
                'business_id' => ['sometimes', 'integer', 'min:1'],
                'boost_tier_key' => ['nullable', 'string', 'max:30'],
                'boost_duration_days' => ['nullable', 'integer', 'min:1', 'max:30'],
                'boost_budget_amount' => ['nullable', 'numeric', 'min:500', 'max:5000'],
                'use_wallet' => ['sometimes', 'boolean'],
            ]);

            $boostTierKey = isset($validated['boost_tier_key']) ? (string) $validated['boost_tier_key'] : null;
            $boostDurationDays = isset($validated['boost_duration_days'])
                ? (int) $validated['boost_duration_days']
                : null;
            $boostBudgetAmount = isset($validated['boost_budget_amount'])
                ? (float) $validated['boost_budget_amount']
                : null;

            if (($boostTierKey === null) xor ($boostDurationDays === null)) {
                return sendResponse(false, 'Provide both boost plan and duration, or omit boost entirely.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($request->boolean('use_wallet')) {
                $walletCheckout = $this->subscriptionService->payPremiumFromWallet(
                    $vendor,
                    $business,
                    $boostTierKey,
                    $boostDurationDays,
                    $boostBudgetAmount,
                );

                return sendResponse(true, 'Premium subscription paid from wallet successfully.', [
                    'business' => new BusinessInfoResource($walletCheckout['business']),
                    'subscription' => $this->subscriptionService->subscriptionPayload($walletCheckout['business']),
                    'wallet_balance' => $walletCheckout['wallet_balance'],
                    'paid_from_wallet' => true,
                ]);
            }

            $checkout = $this->subscriptionService->initPremiumPayment(
                $vendor,
                $business,
                $boostTierKey,
                $boostDurationDays,
                isset($validated['gateway']) ? PaymentGateway::from((string) $validated['gateway']) : null,
                $boostBudgetAmount,
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
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/user/subscription/payment/resume',
        summary: 'Resume a pending premium subscription payment',
        tags: ['Billing'],
        security: [['passport' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pending payment resumed successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'payment', ref: '#/components/schemas/Payment'),
                        new OA\Property(property: 'payments', properties: [
                            new OA\Property(property: 'subscription', ref: '#/components/schemas/Payment'),
                            new OA\Property(property: 'boost', ref: '#/components/schemas/Payment', nullable: true),
                        ], type: 'object'),
                        new OA\Property(property: 'total_amount', type: 'number', format: 'float'),
                        new OA\Property(property: 'currency', type: 'string'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'No pending premium payment found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Business/validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function resumePayment(Request $request)
    {
        try {
            $vendor = $request->user('api');
            $business = $this->businessInfoService->resolveBusinessFromRequest($request);

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
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/user/subscription/payment/confirm',
        summary: 'Confirm a premium subscription payment and activate the plan',
        description: 'Confirms the gateway transaction for a payment created via payment/init (or resumed via '
            .'payment/resume) and activates the premium subscription on success.',
        tags: ['Billing'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['payment_id', 'gateway_transaction_id', 'gateway'],
                properties: [
                    new OA\Property(property: 'payment_id', type: 'integer', description: 'Must be an unconsumed subscription payment owned by the vendor.'),
                    new OA\Property(property: 'gateway_transaction_id', type: 'string', maxLength: 255),
                    new OA\Property(property: 'gateway', type: 'string', enum: ['flutterwave', 'paystack']),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Premium subscription activated successfully (or was already active)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'payment', ref: '#/components/schemas/Payment'),
                        new OA\Property(property: 'subscription', ref: '#/components/schemas/Subscription'),
                        new OA\Property(property: 'business', type: 'object'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Payment not found/owned, or business/validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
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
                'gateway' => ['required', 'string', Rule::in(PaymentGateway::values())],
            ], [
                'payment_id.exists' => 'Checkout expired. Tap Pay again to start a new premium payment.',
            ]);

            $payment = $this->paymentService->findOwnedPayment(
                (int) $validated['payment_id'],
                $vendor,
                PaymentPurpose::Subscription,
            );

            $gatewayTransactionId = trim((string) $validated['gateway_transaction_id']);
            $gateway = PaymentGateway::from((string) $validated['gateway']);
            Log::info('Gateway', ['gateway' => $gateway]);

            $business = $this->paymentReconciliation->completeSubscriptionCheckout(
                $payment,
                $vendor,
                $gatewayTransactionId,
                $gateway,
                verifyWithGateway: $gateway === PaymentGateway::Paystack,
            );
            $business->load(['category:id,name,subcategories', 'location:id,lga_name,state_name,city_name,country_name']);

            return sendResponse(true, 'Premium subscription activated successfully.', [
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

    public function reconcilePayment(Request $request)
    {
        try {
            $vendor = $request->user('api');
            $business = $this->businessInfoService->resolveBusinessFromRequest($request);

            $validated = $request->validate([
                'paystack_reference' => ['required', 'string', 'max:255'],
                'business_id' => ['sometimes', 'integer', 'min:1'],
            ]);

            $result = $this->paymentReconciliation->reconcilePaystackReference(
                (string) $validated['paystack_reference'],
                $business,
            );

            $payment = $result['payment'];
            if ($payment->user_id !== $vendor->id) {
                return sendResponse(false, 'This payment does not belong to your account.', null, Response::HTTP_FORBIDDEN);
            }

            $business = $result['business'];
            if ($business === null) {
                return sendResponse(false, 'Premium could not be activated.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $business->load(['category:id,name,subcategories', 'location:id,lga_name,state_name,city_name,country_name']);

            return sendResponse(true, 'Premium subscription activated successfully.', [
                'payment' => $this->paymentService->toArray($payment),
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
