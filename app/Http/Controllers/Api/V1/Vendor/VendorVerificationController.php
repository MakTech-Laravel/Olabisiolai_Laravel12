<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Services\BusinessInfoService;
use App\Services\PaymentService;
use App\Services\PricingPackageService;
use App\Services\VerificationService;
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
            $business = $this->businessInfoService->findForUser($vendor);

            if ($business === null) {
                return sendResponse(false, 'No business profile found. Please create a business profile first.', null, Response::HTTP_NOT_FOUND);
            }

            if (! $this->verificationService->canApply($business)) {
                return sendResponse(
                    false,
                    'Your business is not eligible to pay for verification at this time. Current status: ' . $business->verification_status->label(),
                    null,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $validated = $request->validate([
                'package_id' => ['required', 'string', Rule::in($this->pricingPackageService->verificationPackageKeys())],
            ]);

            $payment = $this->paymentService->initPayment(
                $vendor,
                $business,
                PaymentPurpose::Verification,
                (string) $validated['package_id'],
            );

            return sendResponse(true, 'Payment initialized successfully.', [
                'payment' => $this->paymentService->toArray($payment),
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

    public function confirmPayment(Request $request)
    {
        try {
            $vendor = $request->user('api');

            $validated = $request->validate([
                'payment_id' => ['required', 'integer', 'exists:payments,id'],
                'gateway_transaction_id' => ['required', 'string', 'max:255'],
            ]);

            $payment = $this->paymentService->findOwnedPayment(
                (int) $validated['payment_id'],
                $vendor,
                PaymentPurpose::Verification,
            );

            if ($payment->status === PaymentStatus::Pending) {
                $payment = $this->paymentService->confirmPayment(
                    $payment,
                    trim((string) $validated['gateway_transaction_id']),
                );
            }

            $business = $this->businessInfoService->findForUser($vendor);
            $awaitingDocumentSubmission = false;
            $consumablePaymentId = null;

            if ($business !== null) {
                $statusData = $this->verificationService->getVendorVerificationStatus($business);
                $awaitingDocumentSubmission = (bool) ($statusData['awaiting_document_submission'] ?? false);
                $consumablePaymentId = $statusData['consumable_payment_id'] ?? null;
            }

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
            $business = $this->businessInfoService->findForUser($vendor);

            if ($business === null) {
                return sendResponse(false, 'No business profile found. Please create a business profile first.', null, Response::HTTP_NOT_FOUND);
            }

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
                'documents' => collect($created)->map(fn($doc) => [
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
            $business = $this->businessInfoService->findForUser($vendor);

            if ($business === null) {
                return sendResponse(false, 'No business profile found.', null, Response::HTTP_NOT_FOUND);
            }

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
            $business = $this->businessInfoService->findForUser($vendor);

            if ($business === null) {
                return sendResponse(false, 'No business profile found.', null, Response::HTTP_NOT_FOUND);
            }

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
