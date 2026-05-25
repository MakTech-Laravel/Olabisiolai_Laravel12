<?php

namespace App\Services;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\VerificationDocumentStatus;
use App\Enums\VerificationNoteType;
use App\Enums\VerificationStatus;
use App\Enums\VerificationWorkflowStatus;
use App\Enums\VerificationWorkflowType;
use App\Http\Traits\FileManagementTrait;
use App\Models\Admin;
use App\Models\BusinessInfo;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\VerificationDocument;
use App\Models\VerificationNote;
use App\Models\VerificationWorkflow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class VerificationService
{
    use FileManagementTrait;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly RealtimeNotificationService $realtimeNotifications,
    ) {}

    public function canApply(BusinessInfo $business): bool
    {
        if ($business->verification_status === VerificationStatus::Approved) {
            return false;
        }

        if ($business->verification_status === VerificationStatus::Pending && ! $business->is_flagged) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<array{document: UploadedFile, document_type: string, title: string, description: ?string}>  $documents
     */
    public function applyForVerification(
        User $vendor,
        BusinessInfo $business,
        Payment $payment,
        array $documents,
    ): array {
        if (! $this->canApply($business)) {
            throw new RuntimeException('This business is not eligible to apply for verification at this time.');
        }

        if ($payment->purpose !== PaymentPurpose::Verification) {
            throw new RuntimeException('Invalid payment for verification.');
        }

        if ($payment->business_info_id !== $business->id) {
            throw new RuntimeException('Payment does not belong to this business.');
        }

        if (! $payment->isConsumable()) {
            throw new RuntimeException('Complete payment before submitting your verification request.');
        }

        if ($documents === []) {
            throw new RuntimeException('At least one document is required.');
        }

        $folderPath = 'businesses/' . $business->id . '/verification';
        $uploadedPaths = [];

        try {
            return DB::transaction(function () use ($vendor, $business, $payment, $documents, $folderPath, &$uploadedPaths): array {
                $created = [];

                foreach ($documents as $item) {
                    $document = $item['document'];
                    $title = $item['title'];
                    $filePath = $this->handleFileUpload($document, $folderPath, $title);
                    $uploadedPaths[] = $filePath;

                    $created[] = VerificationDocument::query()->create([
                        'business_info_id' => $business->id,
                        'uploaded_by' => $vendor->id,
                        'document_type' => $item['document_type'],
                        'title' => $title,
                        'description' => $item['description'],
                        'file_path' => $filePath,
                        'file_name' => $document->getClientOriginalName(),
                        'mime_type' => $document->getMimeType(),
                        'file_size' => $document->getSize(),
                        'status' => VerificationDocumentStatus::Pending,
                    ]);
                }

                VerificationWorkflow::query()->create([
                    'business_info_id' => $business->id,
                    'triggered_by' => $vendor->id,
                    'workflow_type' => VerificationWorkflowType::InitialSubmission,
                    'status' => VerificationWorkflowStatus::Pending,
                    'title' => 'Verification Application Submitted',
                    'description' => 'Vendor submitted verification documents after payment.',
                ]);

                $business->update([
                    'verification_status' => VerificationStatus::Pending,
                    'is_flagged' => false,
                ]);

                $this->paymentService->consumePayment($payment);

                $business->loadMissing('user:id,name');
                $vendorName = (string) ($business->user?->name ?? 'Vendor');

                $this->realtimeNotifications->verificationSubmittedToAdmins(
                    businessInfoId: (int) $business->id,
                    businessName: (string) $business->business_name,
                    vendorName: $vendorName,
                );

                return $created;
            });
        } catch (Throwable $e) {
            foreach ($uploadedPaths as $path) {
                $this->fileDelete($path);
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getVendorVerificationStatus(BusinessInfo $business): array
    {
        $business->load([
            'verificationDocuments' => fn($query) => $query->latest()->with('uploadedBy:id,name'),
            'verificationNotes' => fn($query) => $query->where('is_visible_to_vendor', true)->latest(),
        ]);

        $purchasedPackage = $this->resolvePurchasedVerificationPackage($business);
        $consumablePayment = $this->resolveConsumableVerificationPayment($business);
        $awaitingDocumentSubmission = $this->isAwaitingDocumentSubmission($business, $consumablePayment);

        return [
            'verification_status' => $business->verification_status->value,
            'verification_status_label' => $this->displayStatusLabel($business),
            'is_flagged' => (bool) $business->is_flagged,
            'is_approved' => $business->verification_status === VerificationStatus::Approved,
            'verified_at' => $business->verified_at ? humanDateTime($business->verified_at) : null,
            'awaiting_document_submission' => $awaitingDocumentSubmission,
            'consumable_payment_id' => $awaitingDocumentSubmission ? $consumablePayment?->id : null,
            'purchased_package' => $purchasedPackage,
            'documents' => $business->verificationDocuments->map(fn(VerificationDocument $doc): array => [
                'id' => $doc->id,
                'parent_document_id' => $doc->parent_document_id,
                'document_type' => $doc->document_type,
                'title' => $doc->title,
                'description' => $doc->description,
                'file_name' => $doc->file_name,
                'file_url' => $doc->file_path ? Storage::url($doc->file_path) : null,
                'status' => $doc->status->value,
                'rejection_reason' => $doc->rejection_reason,
                'submitted_at' => humanDateTime($doc->created_at),
            ])->values(),
            'notes' => $business->verificationNotes->map(fn(VerificationNote $note): array => [
                'id' => $note->id,
                'note' => $note->note,
                'created_at' => humanDateTime($note->created_at),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function isAwaitingDocumentSubmission(BusinessInfo $business, ?Payment $consumablePayment): bool
    {
        if ($consumablePayment === null) {
            return false;
        }

        if ($business->verification_status === VerificationStatus::Approved) {
            return false;
        }

        if (
            $business->verification_status === VerificationStatus::Pending
            && ! $business->is_flagged
        ) {
            return false;
        }

        return $business->verificationDocuments->isEmpty();
    }

    private function resolveConsumableVerificationPayment(BusinessInfo $business): ?Payment
    {
        return Payment::query()
            ->where('business_info_id', $business->id)
            ->where('purpose', PaymentPurpose::Verification)
            ->where('status', PaymentStatus::Completed)
            ->where('is_consumed', false)
            ->latest('paid_at')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePurchasedVerificationPackage(BusinessInfo $business): ?array
    {
        $payment = Payment::query()
            ->where('business_info_id', $business->id)
            ->where('purpose', PaymentPurpose::Verification)
            ->where('status', PaymentStatus::Completed)
            ->latest('paid_at')
            ->first();

        if ($payment === null) {
            return null;
        }

        $package = $this->paymentService->findPackage(PaymentPurpose::Verification, $payment->package_id);
        $title = (string) ($package['title'] ?? $payment->metadata['package_title'] ?? $payment->package_id);
        $amount = (float) $payment->amount;
        $currency = $payment->currency;
        $formattedAmount = number_format($amount, 0, '.', ',');

        $usageMessage = match (true) {
            $business->verification_status === VerificationStatus::Approved => sprintf(
                'You verified your business using the %s plan (₦%s). This package was used for your approved application.',
                $title,
                $formattedAmount,
            ),
            $payment->is_consumed => sprintf(
                'You paid for the %s plan (₦%s). This package is linked to your verification application — track document review on your status page.',
                $title,
                $formattedAmount,
            ),
            default => sprintf(
                'You paid for the %s plan (₦%s). Upload your documents to start verification with this package.',
                $title,
                $formattedAmount,
            ),
        };

        return [
            'id' => $payment->package_id,
            'title' => $title,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $package['description'] ?? null,
            'paid_at' => $payment->paid_at ? humanDateTime($payment->paid_at) : null,
            'is_consumed' => (bool) $payment->is_consumed,
            'usage_message' => $usageMessage,
        ];
    }

    public function paginateForAdmin(
        ?string $verificationStatus = null,
        ?string $search = null,
        int $perPage = 10,
    ): LengthAwarePaginator {
        $submissionOrderSql = 'COALESCE(
            (SELECT MAX(created_at) FROM verification_workflows WHERE business_info_id = business_info.id),
            (SELECT MAX(created_at) FROM verification_documents WHERE business_info_id = business_info.id),
            business_info.updated_at
        )';

        return BusinessInfo::query()
            ->with([
                'user:id,first_name,last_name,name,email,phone,role',
                'category:id,name,subcategories',
                'verificationDocuments' => fn($q) => $q->latest()->limit(1),
            ])
            ->withMax('verificationWorkflows', 'created_at')
            ->withMax('verificationDocuments', 'created_at')
            ->where(function ($query): void {
                $query->whereIn('verification_status', [
                    VerificationStatus::Pending,
                    VerificationStatus::Approved,
                ])->orWhere('is_flagged', true);
            })
            ->when($verificationStatus === 'flagged', fn($q) => $q->where('is_flagged', true))
            ->when($verificationStatus === 'pending', fn($q) => $q
                ->where('verification_status', VerificationStatus::Pending)
                ->where('is_flagged', false))
            ->when($verificationStatus === 'queue', fn($q) => $q->where(function ($inner): void {
                $inner->where(function ($pending): void {
                    $pending->where('verification_status', VerificationStatus::Pending)
                        ->where('is_flagged', false);
                })->orWhere('verification_status', VerificationStatus::Approved);
            }))
            ->when(
                $verificationStatus !== null
                    && ! in_array($verificationStatus, ['flagged', 'pending', 'queue', 'all'], true),
                fn($q) => $q->where('verification_status', $verificationStatus),
            )
            ->when($search !== null && trim($search) !== '', function ($query) use ($search): void {
                $term = trim($search);
                $query->where(function ($q) use ($term): void {
                    $q->where('business_name', 'like', "%{$term}%")
                        ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc(DB::raw($submissionOrderSql))
            ->paginate($perPage);
    }

    public function getVerificationDetailsForAdmin(int $businessInfoId): BusinessInfo
    {
        return BusinessInfo::query()
            ->with([
                'user:id,first_name,last_name,name,email,phone,role',
                'category:id,name,subcategories',
                'verifiedBy:id,name,email',
                'verificationDocuments' => fn($q) => $q->latest()->with('uploadedBy:id,name,email'),
                'verificationNotes' => fn($q) => $q->latest()->with('addedBy:id,name,email'),
                'verificationWorkflows' => fn($q) => $q->latest()->with([
                    'triggeredBy:id,name,email',
                    'assignedTo:id,name,email',
                ]),
                'payments' => fn($q) => $q
                    ->where('purpose', PaymentPurpose::Verification)
                    ->latest(),
            ])
            ->findOrFail($businessInfoId);
    }

    public function reviewDocument(int $documentId, string $action, ?string $reason = null): VerificationDocument
    {
        $document = VerificationDocument::query()->findOrFail($documentId);

        if ($document->status !== VerificationDocumentStatus::Pending) {
            throw new RuntimeException('Only pending documents can be reviewed.');
        }

        if ($action === 'reject' && ($reason === null || trim($reason) === '')) {
            throw new RuntimeException('A reason is required when rejecting a document.');
        }

        $document->update([
            'status' => $action === 'approve'
                ? VerificationDocumentStatus::Approved
                : VerificationDocumentStatus::Rejected,
            'rejection_reason' => $action === 'reject' ? trim((string) $reason) : null,
        ]);

        return $document->fresh(['uploadedBy:id,name,email']);
    }

    public function uploadDocument(
        User $vendor,
        BusinessInfo $business,
        string $documentType,
        string $title,
        ?string $description,
        UploadedFile $file,
        ?int $parentDocumentId = null,
    ): VerificationDocument {
        if ($business->verification_status === VerificationStatus::Approved) {
            throw new RuntimeException('Your business is already verified.');
        }

        if ($business->is_flagged || $business->verification_status === VerificationStatus::None) {
            throw new RuntimeException(
                'Please complete verification payment before uploading documents.',
            );
        }

        if ($business->verification_status !== VerificationStatus::Pending) {
            throw new RuntimeException('You cannot upload documents at this time.');
        }

        $parentDocument = null;

        if ($parentDocumentId !== null) {
            $parentDocument = VerificationDocument::query()
                ->where('id', $parentDocumentId)
                ->where('business_info_id', $business->id)
                ->first();

            if ($parentDocument === null) {
                throw new RuntimeException('The document you are replacing was not found.');
            }

            if ($parentDocument->status !== VerificationDocumentStatus::Rejected) {
                throw new RuntimeException('Only rejected documents can be replaced with a new upload.');
            }

            if ($parentDocument->document_type !== $documentType) {
                throw new RuntimeException('Replacement document type must match the original.');
            }
        }

        $folderPath = 'businesses/' . $business->id . '/verification';
        $filePath = $this->handleFileUpload($file, $folderPath, $title);

        try {
            return VerificationDocument::query()->create([
                'business_info_id' => $business->id,
                'uploaded_by' => $vendor->id,
                'parent_document_id' => $parentDocument?->id,
                'document_type' => $documentType,
                'title' => $title,
                'description' => $description,
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'status' => VerificationDocumentStatus::Pending,
            ]);
        } catch (Throwable $e) {
            $this->fileDelete($filePath);

            throw $e;
        }
    }

    public function countPendingDocuments(BusinessInfo $business): int
    {
        return VerificationDocument::query()
            ->where('business_info_id', $business->id)
            ->where('status', VerificationDocumentStatus::Pending)
            ->count();
    }

    public function approveAllPendingDocuments(BusinessInfo $business, Authenticatable $admin, ?string $note): BusinessInfo
    {
        return DB::transaction(function () use ($business, $admin, $note): BusinessInfo {
            $approvedDocCount = $this->bulkApprovePendingDocuments($business->id);

            if ($approvedDocCount > 0) {
                VerificationNote::query()->create([
                    'business_info_id' => $business->id,
                    'added_by' => $this->noteAuthorId($admin),
                    'note_type' => VerificationNoteType::AdminDecision,
                    'note' => $note ?? "{$approvedDocCount} document(s) approved.",
                    'is_visible_to_vendor' => true,
                ]);
            }

            return $this->getVerificationDetailsForAdmin($business->id);
        });
    }

    public function approveVerification(BusinessInfo $business, Authenticatable $admin, ?string $note): BusinessInfo
    {
        return DB::transaction(function () use ($business, $admin, $note): BusinessInfo {
            $approvedDocCount = $this->bulkApprovePendingDocuments($business->id);

            $business->update([
                'verification_status' => VerificationStatus::Approved,
                'is_flagged' => false,
                'verified_by' => $admin instanceof User ? $admin->id : null,
                'verified_at' => now(),
                'verification_note' => $note,
            ]);

            VerificationNote::query()->create([
                'business_info_id' => $business->id,
                'added_by' => $this->noteAuthorId($admin),
                'note_type' => VerificationNoteType::AdminDecision,
                'note' => $note ?? (
                    $approvedDocCount > 0
                    ? "Verification approved ({$approvedDocCount} document(s) approved together.)"
                    : 'Verification approved.'
                ),
                'is_visible_to_vendor' => true,
            ]);

            VerificationWorkflow::query()
                ->where('business_info_id', $business->id)
                ->where('status', VerificationWorkflowStatus::Pending)
                ->update([
                    'status' => VerificationWorkflowStatus::Completed,
                    'completed_at' => now(),
                    'completion_notes' => 'Verification approved by admin.',
                ]);

            $fresh = $business->fresh(['user:id,name,email', 'verifiedBy:id,name,email']);

            if ($fresh->user !== null) {
                $this->realtimeNotifications->verificationApproved(
                    vendor: $fresh->user,
                    businessName: (string) $fresh->business_name,
                    note: $note,
                );
            }

            return $fresh;
        });
    }

    private function bulkApprovePendingDocuments(int $businessInfoId): int
    {
        return VerificationDocument::query()
            ->where('business_info_id', $businessInfoId)
            ->where('status', VerificationDocumentStatus::Pending)
            ->update([
                'status' => VerificationDocumentStatus::Approved,
                'rejection_reason' => null,
            ]);
    }

    public function flagVerification(BusinessInfo $business, Authenticatable $admin, string $reason): BusinessInfo
    {
        return DB::transaction(function () use ($business, $admin, $reason): BusinessInfo {
            $business->update([
                'verification_status' => VerificationStatus::None,
                'is_flagged' => true,
                'verified_by' => null,
                'verified_at' => null,
                'verification_note' => $reason,
            ]);

            VerificationNote::query()->create([
                'business_info_id' => $business->id,
                'added_by' => $this->noteAuthorId($admin),
                'note_type' => VerificationNoteType::AdminDecision,
                'note' => $reason,
                'is_visible_to_vendor' => true,
            ]);

            VerificationWorkflow::query()
                ->where('business_info_id', $business->id)
                ->where('status', VerificationWorkflowStatus::Pending)
                ->update([
                    'status' => VerificationWorkflowStatus::Failed,
                    'completed_at' => now(),
                    'completion_notes' => 'Verification flagged by admin.',
                ]);

            $fresh = $business->fresh(['user:id,name,email', 'verifiedBy:id,name,email']);

            if ($fresh->user !== null) {
                $this->realtimeNotifications->verificationFlagged(
                    vendor: $fresh->user,
                    businessName: (string) $fresh->business_name,
                    reason: $reason,
                );
            }

            return $fresh;
        });
    }

    private function noteAuthorId(Authenticatable $actor): ?int
    {
        return $actor instanceof User ? $actor->id : null;
    }

    public function showsVerifiedBadge(BusinessInfo $business): bool
    {
        return $business->verification_status === VerificationStatus::Approved;
    }

    public function revokeVerificationByAdmin(BusinessInfo $business, Authenticatable $admin, ?string $reason = null): BusinessInfo
    {
        if ($business->verification_status === VerificationStatus::None && ! $business->is_flagged) {
            throw new \RuntimeException('This business is not verified.');
        }

        $noteText = $reason !== null && trim($reason) !== ''
            ? trim($reason)
            : 'Verification revoked by admin.';

        return DB::transaction(function () use ($business, $admin, $noteText): BusinessInfo {
            $business->update([
                'verification_status' => VerificationStatus::None,
                'is_flagged' => false,
                'verified_by' => null,
                'verified_at' => null,
                'verification_note' => $noteText,
            ]);

            VerificationNote::query()->create([
                'business_info_id' => $business->id,
                'added_by' => $this->noteAuthorId($admin),
                'note_type' => VerificationNoteType::AdminDecision,
                'note' => $noteText,
                'is_visible_to_vendor' => true,
            ]);

            VerificationWorkflow::query()
                ->where('business_info_id', $business->id)
                ->whereIn('status', [
                    VerificationWorkflowStatus::Pending,
                    VerificationWorkflowStatus::Completed,
                ])
                ->update([
                    'status' => VerificationWorkflowStatus::Failed,
                    'completed_at' => now(),
                    'completion_notes' => 'Verification revoked by admin.',
                ]);

            $fresh = $business->fresh(['user:id,name,email', 'verifiedBy:id,name,email']);

            if ($fresh->user !== null) {
                $this->realtimeNotifications->verificationRevoked(
                    vendor: $fresh->user,
                    businessName: (string) $fresh->business_name,
                    reason: $noteText,
                );
            }

            return $fresh;
        });
    }

    public function revokeVerificationForMajorBusinessChange(BusinessInfo $business, string $reason): BusinessInfo
    {
        if ($business->verification_status !== VerificationStatus::Approved) {
            return $business;
        }

        return DB::transaction(function () use ($business, $reason): BusinessInfo {
            $business->update([
                'verification_status' => VerificationStatus::None,
                'verified_at' => null,
                'verified_by' => null,
                'verification_note' => $reason,
            ]);

            VerificationNote::query()->create([
                'business_info_id' => $business->id,
                'added_by' => $business->user_id,
                'note_type' => VerificationNoteType::VendorCommunication,
                'note' => $reason,
                'is_visible_to_vendor' => true,
            ]);

            return $business->fresh();
        });
    }

    public function displayStatusLabel(BusinessInfo $business): string
    {
        if ($business->verification_status === VerificationStatus::Approved) {
            return VerificationStatus::Approved->label();
        }

        if ($business->is_flagged) {
            return 'Flagged';
        }

        return $business->verification_status->label();
    }

    public function addNote(
        BusinessInfo $business,
        Authenticatable $admin,
        string $note,
        VerificationNoteType $noteType,
        bool $isVisibleToVendor,
    ): VerificationNote {
        return VerificationNote::query()->create([
            'business_info_id' => $business->id,
            'added_by' => $this->noteAuthorId($admin),
            'note_type' => $noteType,
            'note' => $note,
            'is_visible_to_vendor' => $isVisibleToVendor,
        ]);
    }
}
