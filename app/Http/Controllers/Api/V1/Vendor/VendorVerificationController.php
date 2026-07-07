<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\BusinessInfo;
use App\Models\Payment;
use App\Models\User;
use App\Services\BusinessInfoService;
use App\Services\PaymentService;
use App\Services\PricingPackageService;
use App\Services\VerificationService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VendorVerificationController extends Controller
{
    public function __construct(
        private readonly VerificationService $verificationService,
        private readonly BusinessInfoService $businessInfoService,
        private readonly PaymentService $paymentService,
        private readonly PricingPackageService $pricingPackageService,
        private readonly WalletService $walletService,
    ) {}

    public function packages()
    {
        return sendResponse(true, 'Verification packages retrieved successfully.', [
            'currency' => $this->pricingPackageService->verificationCurrency(),
            'packages' => $this->paymentService->verificationPackages(),
        ]);
    }

    public function initPayment(Request $request)
    {
        try {
            $vendor = $request->user('api');
            $business = $this->businessInfoService->resolveBusinessFromRequest($request);

            $validated = $request->validate([
                'package_id' => ['required', 'string', Rule::in($this->pricingPackageService->verificationPackageKeys())],
                'gateway' => ['nullable', 'string', Rule::in(PaymentGateway::values())],
                'use_wallet' => ['sometimes', 'boolean'],
                'apply_wallet' => ['sometimes', 'boolean'],
            ]);

            $gateway = isset($validated['gateway']) ? PaymentGateway::from((string) $validated['gateway']) : null;
            $pendingPayment = $this->verificationService->findResumableVerificationPayment($business);

            if ($pendingPayment !== null) {
                return $this->respondWithVerificationCheckout(
                    $vendor,
                    $business,
                    $pendingPayment,
                    $request->boolean('apply_wallet'),
                    $gateway,
                );
            }

            if (! $this->verificationService->canInitVerificationPayment($business)) {
                $reason = $this->verificationService->verificationPaymentBlockReason($business)
                    ?? 'Your business is not eligible to pay for verification at this time.';

                return sendResponse(
                    false,
                    $reason,
                    null,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            if ($request->boolean('use_wallet')) {
                $payment = $this->paymentService->initPayment(
                    $vendor,
                    $business,
                    PaymentPurpose::Verification,
                    (string) $validated['package_id'],
                    0,
                    null,
                    $gateway,
                );

                $payment = $this->walletService->payForPendingPayment($vendor, $payment, 'Verification checkout');

                $statusData = $this->verificationService->getVendorVerificationStatus($business);

                return sendResponse(true, 'Payment confirmed successfully.', [
                    'payment' => $this->paymentService->toArray($payment->fresh()),
                    'awaiting_document_submission' => (bool) ($statusData['awaiting_document_submission'] ?? false),
                    'consumable_payment_id' => $statusData['consumable_payment_id'] ?? null,
                    'paid_from_wallet' => true,
                ], Response::HTTP_CREATED);
            }

            $payment = $this->paymentService->initPayment(
                $vendor,
                $business,
                PaymentPurpose::Verification,
                (string) $validated['package_id'],
                0,
                null,
                $gateway,
            );

            return $this->respondWithVerificationCheckout(
                $vendor,
                $business,
                $payment,
                $request->boolean('apply_wallet'),
                $gateway,
            );
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function respondWithVerificationCheckout(
        User $vendor,
        BusinessInfo $business,
        Payment $payment,
        bool $applyWallet,
        ?PaymentGateway $gateway,
    ) {
        if ($gateway !== null && $payment->gateway !== $gateway) {
            $payment->update(['gateway' => $gateway]);
            $payment = $payment->fresh();
        }

        $walletApplication = $this->walletService->readApplicationFromPayment($payment, (float) $payment->amount);

        if ($applyWallet && (float) ($walletApplication['wallet_applied'] ?? 0) <= 0) {
            $walletApplication = $this->walletService->attachApplicationToPayment(
                $payment,
                $vendor,
                (float) $payment->amount,
            );
            $payment = $payment->fresh();
        }

        if ((float) ($walletApplication['gateway_amount'] ?? $payment->amount) <= 0) {
            $this->walletService->settleApplication($vendor, $payment, 'Verification checkout');
            $payment->update([
                'status' => PaymentStatus::Completed,
                'paid_at' => now(),
                'gateway_transaction_id' => 'wallet_'.$payment->tx_ref,
                'gateway' => PaymentGateway::Wallet,
            ]);
            $statusData = $this->verificationService->getVendorVerificationStatus($business);

            return sendResponse(true, 'Payment confirmed successfully.', [
                'payment' => $this->paymentService->toArray($payment->fresh()),
                'awaiting_document_submission' => (bool) ($statusData['awaiting_document_submission'] ?? false),
                'consumable_payment_id' => $statusData['consumable_payment_id'] ?? null,
                'paid_from_wallet' => true,
            ], Response::HTTP_CREATED);
        }

        return sendResponse(true, 'Payment initialized successfully.', [
            'payment' => $this->paymentService->toArray($payment),
            'total_amount' => (float) $payment->amount,
            'gateway_amount' => $walletApplication['gateway_amount'] ?? (float) $payment->amount,
            'wallet_applied' => $walletApplication['wallet_applied'] ?? 0,
            'wallet_balance' => $walletApplication['wallet_balance'] ?? null,
        ], Response::HTTP_CREATED);
    }

    public function confirmPayment(Request $request)
    {
        try {
            $vendor = $request->user('api');

            $validated = $request->validate([
                'payment_id' => ['required', 'integer', 'exists:payments,id'],
                'gateway_transaction_id' => ['required', 'string', 'max:255'],
                'gateway' => ['required', 'string', Rule::in(PaymentGateway::values())],
            ]);

            $payment = $this->paymentService->findOwnedPayment(
                (int) $validated['payment_id'],
                $vendor,
                PaymentPurpose::Verification,
            );

            $gateway = PaymentGateway::from((string) $validated['gateway']);
            if ($payment->status === PaymentStatus::Pending) {
                $this->walletService->settleApplication($vendor, $payment, 'Verification checkout');
                $payment = $this->paymentService->confirmPayment(
                    $payment,
                    trim((string) $validated['gateway_transaction_id']),
                    $gateway,
                );
            } elseif ($payment->gateway === null) {
                $payment->update(['gateway' => $gateway]);
                $payment = $payment->fresh();
            }

            $business = $this->businessInfoService->resolveBusinessFromRequest($request);
            $awaitingDocumentSubmission = false;
            $consumablePaymentId = null;

            $statusData = $this->verificationService->getVendorVerificationStatus($business);
            $awaitingDocumentSubmission = (bool) ($statusData['awaiting_document_submission'] ?? false);
            $consumablePaymentId = $statusData['consumable_payment_id'] ?? null;

            return sendResponse(true, 'Payment confirmed successfully.', [
                'payment' => $this->paymentService->toArray($payment->fresh()),
                'awaiting_document_submission' => $awaitingDocumentSubmission,
                'consumable_payment_id' => $consumablePaymentId,
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

    public function apply(Request $request)
    {
        try {
            $vendor = $request->user('api');
            $business = $this->businessInfoService->resolveBusinessFromRequest($request);

            $validated = $request->validate([
                'payment_id' => ['required', 'integer', 'exists:payments,id'],
                'documents' => ['required', 'array', 'min:1', 'max:10'],
                'documents.*.document_type' => ['required', 'string', 'in:payment_receipt,bank_transfer,business_registration,cac_document,identity_proof,address_proof,other'],
                'documents.*.title' => ['required', 'string', 'max:255'],
                'documents.*.description' => ['nullable', 'string', 'max:1000'],
                'documents.*.document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);

            $payment = $this->paymentService->findConsumablePayment(
                (int) $validated['payment_id'],
                $vendor,
                PaymentPurpose::Verification,
            );

            $documentPayload = [];

            foreach ($validated['documents'] as $doc) {
                $documentPayload[] = [
                    'document' => $doc['document'],
                    'document_type' => (string) $doc['document_type'],
                    'title' => trim((string) $doc['title']),
                    'description' => isset($doc['description']) ? trim((string) $doc['description']) : null,
                ];
            }

            $created = $this->verificationService->applyForVerification(
                $vendor,
                $business,
                $payment,
                $documentPayload,
            );

            return sendResponse(true, 'Verification application submitted successfully. We will review your documents shortly.', [
                'documents' => collect($created)->map(fn ($doc) => [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type,
                    'title' => $doc->title,
                    'file_name' => $doc->file_name,
                    'status' => $doc->status->value,
                    'submitted_at' => humanDateTime($doc->created_at),
                ])->values(),
                'verification_status' => $business->fresh()->verification_status->value,
            ], Response::HTTP_CREATED);
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function status(Request $request)
    {
        try {
            $vendor = $request->user('api');
            $business = $this->businessInfoService->resolveBusinessFromRequest($request);

            $statusData = $this->verificationService->getVendorVerificationStatus($business);

            return sendResponse(true, 'Verification status retrieved successfully.', $statusData);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function uploadDocument(Request $request)
    {
        try {
            $vendor = $request->user('api');
            $business = $this->businessInfoService->resolveBusinessFromRequest($request);

            $validated = $request->validate([
                'document_type' => ['required', 'string', 'in:payment_receipt,bank_transfer,business_registration,cac_document,identity_proof,address_proof,other'],
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:1000'],
                'parent_document_id' => ['nullable', 'integer', 'exists:verification_documents,id'],
                'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);

            $document = $this->verificationService->uploadDocument(
                $vendor,
                $business,
                (string) $validated['document_type'],
                trim((string) $validated['title']),
                isset($validated['description']) ? trim((string) $validated['description']) : null,
                $validated['document'],
                isset($validated['parent_document_id']) ? (int) $validated['parent_document_id'] : null,
            );

            return sendResponse(true, 'Document uploaded successfully. It will be reviewed shortly.', [
                'document' => [
                    'id' => $document->id,
                    'document_type' => $document->document_type,
                    'title' => $document->title,
                    'file_name' => $document->file_name,
                    'status' => $document->status->value,
                    'submitted_at' => humanDateTime($document->created_at),
                ],
            ], Response::HTTP_CREATED);
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
