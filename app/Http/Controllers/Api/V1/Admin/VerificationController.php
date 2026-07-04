<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\VerificationNoteType;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VerificationResource;
use App\Services\PaymentService;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VerificationController extends Controller
{
    public function __construct(private readonly VerificationService $verificationService) {}

    public function index(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'verification_status' => ['nullable', 'string', Rule::in([...VerificationStatus::values(), 'flagged', 'queue', 'all', 'needs_reverification'])],
                'search' => ['nullable', 'string', 'max:255'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $records = $this->verificationService->paginateForAdmin(
                $validated['verification_status'] ?? null,
                isset($validated['search']) ? (string) $validated['search'] : null,
                $validated['per_page'] ?? 15,
            );

            return sendResponse(true, 'Verification requests retrieved successfully.', [
                'filter' => [
                    'verification_status' => $validated['verification_status'] ?? 'all',
                    'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
                ],
                'count' => $records->total(),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'last_page' => $records->lastPage(),
                    'total' => $records->total(),
                ],
                'verifications' => VerificationResource::collection($records),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function view(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', 'exists:business_info,id'],
            ]);

            $business = $this->verificationService->getVerificationDetailsForAdmin((int) $validated['business_info_id']);

            return sendResponse(true, 'Verification details retrieved successfully.', [
                'verification' => new VerificationResource($business),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function approve(Request $request)
    {
        try {
            $admin = adminAuthCheck($request);

            if (! $admin) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', 'exists:business_info,id'],
                'note' => ['nullable', 'string', 'max:2000'],
            ]);

            $business = $this->verificationService->getVerificationDetailsForAdmin((int) $validated['business_info_id']);

            $note = isset($validated['note']) ? trim((string) $validated['note']) : null;

            if ($business->verification_status === VerificationStatus::Approved) {
                $updated = $this->verificationService->approveAllPendingDocuments($business, $admin, $note);

                return sendResponse(true, 'All pending documents approved successfully.', [
                    'verification' => new VerificationResource($updated),
                ]);
            }

            $updated = $this->verificationService->approveVerification(
                $business,
                $admin,
                $note,
            );

            return sendResponse(true, 'All documents and business verification approved successfully.', [
                'verification' => new VerificationResource($updated),
            ]);
        } catch (\RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function flag(Request $request)
    {
        try {
            $admin = adminAuthCheck($request);

            if (! $admin) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', 'exists:business_info,id'],
                'reason' => ['required', 'string', 'min:10', 'max:2000'],
            ]);

            $business = $this->verificationService->getVerificationDetailsForAdmin((int) $validated['business_info_id']);

            if ($business->verification_status !== VerificationStatus::Pending) {
                return sendResponse(false, 'Only pending verification requests can be flagged.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $updated = $this->verificationService->flagVerification(
                $business,
                $admin,
                trim((string) $validated['reason']),
            );

            return sendResponse(true, 'Verification flagged.', [
                'verification' => new VerificationResource($updated),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function reviewDocument(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'document_id' => ['required', 'integer', 'exists:verification_documents,id'],
                'action' => ['required', 'string', Rule::in(['approve', 'reject'])],
                'reason' => ['nullable', 'string', 'max:2000', 'required_if:action,reject'],
            ]);

            $document = $this->verificationService->reviewDocument(
                (int) $validated['document_id'],
                (string) $validated['action'],
                isset($validated['reason']) ? trim((string) $validated['reason']) : null,
            );

            return sendResponse(true, 'Document reviewed successfully.', [
                'document' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'document_type' => $document->document_type,
                    'status' => $document->status->value,
                    'rejection_reason' => $document->rejection_reason,
                ],
            ]);
        } catch (\RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request)
    {
        try {
            $admin = adminAuthCheck($request);

            if (! $admin) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', 'exists:business_info,id'],
                'reason' => ['nullable', 'string', 'max:2000'],
            ]);

            $business = $this->verificationService->getVerificationDetailsForAdmin((int) $validated['business_info_id']);

            $reason = isset($validated['reason']) ? trim((string) $validated['reason']) : null;

            $updated = $this->verificationService->revokeVerificationByAdmin($business, $admin, $reason);

            return sendResponse(true, 'Verification removed. Vendor is no longer verified.', [
                'verification' => new VerificationResource($updated),
            ]);
        } catch (\RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function grantReverification(Request $request)
    {
        try {
            $admin = adminAuthCheck($request);

            if (! $admin) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', 'exists:business_info,id'],
                'reason' => ['required', 'string', 'min:3', 'max:2000'],
            ]);

            $business = $this->verificationService->getVerificationDetailsForAdmin((int) $validated['business_info_id']);

            $result = $this->verificationService->grantReVerificationAccess(
                $business,
                $admin,
                trim((string) $validated['reason']),
            );

            return sendResponse(true, 'Free re-verification granted. Vendor can upload documents again.', [
                'payment' => app(PaymentService::class)->toArray($result['payment']),
                'verification' => new VerificationResource(
                    $this->verificationService->getVerificationDetailsForAdmin($business->id),
                ),
            ], Response::HTTP_CREATED);
        } catch (\RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function reapprove(Request $request)
    {
        try {
            $admin = adminAuthCheck($request);

            if (! $admin) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', 'exists:business_info,id'],
                'note' => ['nullable', 'string', 'max:2000'],
            ]);

            $business = $this->verificationService->getVerificationDetailsForAdmin((int) $validated['business_info_id']);

            $updated = $this->verificationService->reapproveVerification(
                $business,
                $admin,
                isset($validated['note']) ? trim((string) $validated['note']) : null,
            );

            return sendResponse(true, 'Verification re-approved and badge restored.', [
                'verification' => new VerificationResource(
                    $this->verificationService->getVerificationDetailsForAdmin($updated->id),
                ),
            ]);
        } catch (\RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function addNote(Request $request)
    {
        try {
            $admin = adminAuthCheck($request);

            if (! $admin) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', 'exists:business_info,id'],
                'note' => ['required', 'string', 'min:3', 'max:5000'],
                'note_type' => ['required', 'string', Rule::in(VerificationNoteType::values())],
                'is_visible_to_vendor' => ['required', 'boolean'],
            ]);

            $business = $this->verificationService->getVerificationDetailsForAdmin((int) $validated['business_info_id']);

            $note = $this->verificationService->addNote(
                $business,
                $admin,
                trim((string) $validated['note']),
                VerificationNoteType::from((string) $validated['note_type']),
                (bool) $validated['is_visible_to_vendor'],
            );

            return sendResponse(true, 'Note added successfully.', [
                'note' => [
                    'id' => $note->id,
                    'business_info_id' => $note->business_info_id,
                    'note_type' => $note->note_type->value,
                    'note' => $note->note,
                    'is_visible_to_vendor' => $note->is_visible_to_vendor,
                    'created_at' => humanDateTime($note->created_at),
                ],
            ], Response::HTTP_CREATED);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
