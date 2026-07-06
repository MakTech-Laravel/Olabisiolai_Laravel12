<?php

namespace App\Services;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\VerificationDocumentStatus;
use App\Enums\VerificationNoteType;
use App\Enums\VerificationStatus;
use App\Enums\VerificationWorkflowStatus;
use App\Enums\VerificationWorkflowType;
use App\Http\Traits\FileManagementTrait;
use App\Models\BusinessInfo;
use App\Models\Payment;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\VerificationNote;
use App\Models\VerificationWorkflow;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class VerificationService
{
    use FileManagementTrait;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly RealtimeNotificationService $realtimeNotifications,
        private readonly ReferralService $referralService,
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

    public function canInitVerificationPayment(BusinessInfo $business): bool
    {
        if ($business->verification_status === VerificationStatus::Approved) {
            return false;
        }

        if ($business->verification_status === VerificationStatus::Pending && ! $business->is_flagged) {
            return false;
        }

        if ($this->resolveConsumableVerificationPayment($business) !== null) {
            return false;
        }

        if ($this->hasPendingVerificationPayment($business)) {
            return false;
        }

        if ($this->needsAdminReapproval($business)) {
            return false;
        }

        return true;
    }

    public function verificationPaymentBlockReason(BusinessInfo $business): ?string
    {
        if ($business->verification_status === VerificationStatus::Approved) {
            return 'Your business is already verified.';
        }

        if ($business->verification_status === VerificationStatus::Pending && ! $business->is_flagged) {
            return 'Your verification application is under admin review. You cannot start a new payment.';
        }

        $consumable = $this->resolveConsumableVerificationPayment($business);
        if ($consumable !== null) {
            if ($this->needsVendorDocumentAction($business)) {
                return 'You have verification credit on file. Upload replacement documents for rejected files on your document status page — do not pay again.';
            }

            if ($this->isReverificationWaiverPayment($consumable)) {
                return 'You already have a free re-verification credit. Upload your documents — do not pay again.';
            }

            return 'You already have a paid verification ready. Upload your documents — do not pay again.';
        }

        if ($this->hasPendingVerificationPayment($business)) {
            return 'You already have a pending verification checkout. Complete or cancel it before starting another payment.';
        }

        if ($this->needsAdminReapproval($business)) {
            return 'Your documents are on file after a profile update. An admin will re-approve your verification — you do not need to pay again.';
        }

        return null;
    }

    public function needsAdminReapproval(BusinessInfo $business): bool
    {
        if ($business->verification_status !== VerificationStatus::None || $business->is_flagged) {
            return false;
        }

        if (! $this->hasPriorVerificationHistory($business)) {
            return false;
        }

        if ($this->resolveConsumableVerificationPayment($business) !== null) {
            return false;
        }

        return $business->verificationDocuments()->exists();
    }

    private function hasPendingVerificationPayment(BusinessInfo $business): bool
    {
        return Payment::query()
            ->where('business_info_id', $business->id)
            ->where('purpose', PaymentPurpose::Verification)
            ->where('status', PaymentStatus::Pending)
            ->exists();
    }

    private function isReverificationWaiverPayment(Payment $payment): bool
    {
        $metadata = is_array($payment->metadata) ? $payment->metadata : [];

        return (bool) ($metadata['reverification_waiver'] ?? false);
    }

    private function hasApprovedDocumentsOnFile(BusinessInfo $business): bool
    {
        return VerificationDocument::query()
            ->where('business_info_id', $business->id)
            ->where('status', VerificationDocumentStatus::Approved)
            ->exists();
    }

    private function hasRejectedDocuments(BusinessInfo $business): bool
    {
        return VerificationDocument::query()
            ->where('business_info_id', $business->id)
            ->where('status', VerificationDocumentStatus::Rejected)
            ->exists();
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

        $folderPath = 'businesses/'.$business->id.'/verification';
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
            'verificationDocuments' => fn ($query) => $query->latest()->with('uploadedBy:id,name'),
            'verificationNotes' => fn ($query) => $query->where('is_visible_to_vendor', true)->latest(),
        ]);

        $purchasedPackage = $this->resolvePurchasedVerificationPackage($business);
        $consumablePayment = $this->resolveConsumableVerificationPayment($business);
        $awaitingDocumentSubmission = $this->isAwaitingDocumentSubmission($business, $consumablePayment);
        $needsAdminReapproval = $this->needsAdminReapproval($business);
        $canInitPayment = $this->canInitVerificationPayment($business);

        return [
            'verification_status' => $business->verification_status->value,
            'verification_status_label' => $this->displayStatusLabel($business),
            'is_flagged' => (bool) $business->is_flagged,
            'is_approved' => $business->verification_status === VerificationStatus::Approved,
            'verified_at' => $business->verified_at ? humanDateTime($business->verified_at) : null,
            'awaiting_document_submission' => $awaitingDocumentSubmission,
            'needs_admin_reapproval' => $needsAdminReapproval,
            'needs_document_action' => $this->needsVendorDocumentAction($business),
            'can_upload_documents' => $this->canVendorUploadDocuments($business),
            'has_open_document_review' => $this->hasOpenDocumentReview($business),
            'can_init_payment' => $canInitPayment,
            'payment_block_reason' => $canInitPayment ? null : $this->verificationPaymentBlockReason($business),
            'has_unused_verification_payment' => $consumablePayment !== null,
            'consumable_payment_id' => $awaitingDocumentSubmission ? $consumablePayment?->id : null,
            'purchased_package' => $purchasedPackage,
            'documents' => $business->verificationDocuments->map(fn (VerificationDocument $doc): array => [
                'id' => $doc->id,
                'parent_document_id' => $doc->parent_document_id,
                'document_type' => $doc->document_type,
                'title' => $doc->title,
                'description' => $doc->description,
                'file_name' => $doc->file_name,
                'file_url' => $doc->file_path ? storage_url($doc->file_path) : null,
                'status' => $doc->status->value,
                'rejection_reason' => $doc->rejection_reason,
                'submitted_at' => humanDateTime($doc->created_at),
            ])->values(),
            'notes' => $business->verificationNotes->map(fn (VerificationNote $note): array => [
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

    public function needsVendorDocumentAction(BusinessInfo $business): bool
    {
        return $this->countRejectedLatestDocuments($business) > 0;
    }

    public function canVendorUploadDocuments(BusinessInfo $business): bool
    {
        if ($business->verification_status === VerificationStatus::Approved || $business->is_flagged) {
            return false;
        }

        if ($business->verification_status === VerificationStatus::Pending) {
            return true;
        }

        if ($business->verification_status !== VerificationStatus::None) {
            return false;
        }

        if ($this->resolveConsumableVerificationPayment($business) !== null) {
            return true;
        }

        return $this->needsVendorDocumentAction($business);
    }

    public function hasOpenDocumentReview(BusinessInfo $business): bool
    {
        if ($business->is_flagged) {
            return false;
        }

        if ($business->verification_status === VerificationStatus::Pending) {
            return true;
        }

        if ($business->verification_status !== VerificationStatus::None) {
            return false;
        }

        if (! $this->hasPriorVerificationHistory($business)) {
            return false;
        }

        if ($this->countPendingDocuments($business) > 0) {
            return true;
        }

        if ($this->needsVendorDocumentAction($business)) {
            return true;
        }

        if ($this->resolveConsumableVerificationPayment($business) !== null) {
            $latest = $this->latestDocumentsByType((int) $business->id);

            return $latest->isNotEmpty() && ! $this->allLatestDocumentsApproved($business);
        }

        return $this->needsAdminReapproval($business);
    }

    public function allLatestDocumentsApproved(BusinessInfo $business): bool
    {
        $latest = $this->latestDocumentsByType((int) $business->id);
        $requiredTypes = ['business_registration', 'identity_proof', 'address_proof'];

        foreach ($requiredTypes as $type) {
            if (! $latest->has($type)) {
                return false;
            }

            if ($latest->get($type)->status !== VerificationDocumentStatus::Approved) {
                return false;
            }
        }

        return true;
    }

    private function resumeVerificationReview(BusinessInfo $business): void
    {
        if ($business->verification_status === VerificationStatus::None && ! $business->is_flagged) {
            $business->update(['verification_status' => VerificationStatus::Pending]);
        }
    }

    public function resolveConsumableVerificationPayment(BusinessInfo $business): ?Payment
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
            ->orderByRaw('CASE WHEN is_consumed = 0 THEN 0 ELSE 1 END')
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
            $this->needsAdminReapproval($business) => 'Your verification was reset after a profile update. Your documents are on file — an admin will re-approve your badge. You do not need to pay again.',
            ! $payment->is_consumed && $this->isReverificationWaiverPayment($payment) => sprintf(
                'Admin granted free re-verification (%s). Upload your documents to continue — do not pay again.',
                $title,
            ),
            ! $payment->is_consumed => sprintf(
                'You paid for the %s plan (₦%s). Upload your documents to start verification with this package.',
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
                'verificationDocuments' => fn ($q) => $q->latest()->limit(1),
            ])
            ->withMax('verificationWorkflows', 'created_at')
            ->withMax('verificationDocuments', 'created_at')
            ->where(function ($query): void {
                $query->whereIn('verification_status', [
                    VerificationStatus::Pending,
                    VerificationStatus::Approved,
                ])
                    ->orWhere('is_flagged', true)
                    ->orWhereHas('verificationDocuments')
                    ->orWhereHas('verificationNotes')
                    ->orWhereHas('payments', fn ($paymentQuery) => $paymentQuery
                        ->where('purpose', PaymentPurpose::Verification));
            })
            ->when($verificationStatus === 'flagged', fn ($q) => $q->where('is_flagged', true))
            ->when($verificationStatus === 'needs_reverification', fn ($q) => $q
                ->where('verification_status', VerificationStatus::None)
                ->where('is_flagged', false)
                ->where(function ($inner): void {
                    $inner->whereHas('verificationDocuments')
                        ->orWhereHas('payments', fn ($paymentQuery) => $paymentQuery
                            ->where('purpose', PaymentPurpose::Verification)
                            ->where('status', PaymentStatus::Completed));
                }))
            ->when($verificationStatus === 'pending', fn ($q) => $q
                ->where('verification_status', VerificationStatus::Pending)
                ->where('is_flagged', false))
            ->when($verificationStatus === 'queue', fn ($q) => $q->where(function ($inner): void {
                $inner->where(function ($pending): void {
                    $pending->where('verification_status', VerificationStatus::Pending)
                        ->where('is_flagged', false);
                })
                    ->orWhere('verification_status', VerificationStatus::Approved)
                    ->orWhere(function ($needsReview): void {
                        $needsReview->where('verification_status', VerificationStatus::None)
                            ->where('is_flagged', false)
                            ->where(function ($history): void {
                                $history->whereHas('verificationDocuments')
                                    ->orWhereHas('payments', fn ($paymentQuery) => $paymentQuery
                                        ->where('purpose', PaymentPurpose::Verification)
                                        ->where('status', PaymentStatus::Completed));
                            });
                    });
            }))
            ->when(
                $verificationStatus !== null
                    && ! in_array($verificationStatus, ['flagged', 'pending', 'queue', 'all', 'needs_reverification'], true),
                fn ($q) => $q->where('verification_status', $verificationStatus),
            )
            ->when($search !== null && trim($search) !== '', function ($query) use ($search): void {
                $term = trim($search);
                $query->where(function ($q) use ($term): void {
                    $q->where('business_name', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
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
                'verificationDocuments' => fn ($q) => $q->latest()->with('uploadedBy:id,name,email'),
                'verificationNotes' => fn ($q) => $q->latest()->with('addedBy:id,name,email'),
                'verificationWorkflows' => fn ($q) => $q->latest()->with([
                    'triggeredBy:id,name,email',
                    'assignedTo:id,name,email',
                ]),
                'payments' => fn ($q) => $q
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

        $business = BusinessInfo::query()->find($document->business_info_id);
        if ($business !== null) {
            $this->resumeVerificationReview($business);
        }

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

        if ($business->is_flagged) {
            throw new RuntimeException('Complete a new verification payment before uploading documents.');
        }

        if (! $this->canVendorUploadDocuments($business)) {
            throw new RuntimeException(
                'Please complete verification payment before uploading documents.',
            );
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

        $folderPath = 'businesses/'.$business->id.'/verification';
        $filePath = $this->handleFileUpload($file, $folderPath, $title);

        try {
            $document = VerificationDocument::query()->create([
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

            $this->resumeVerificationReview($business->fresh());

            return $document;
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

    public function countRejectedLatestDocuments(BusinessInfo $business): int
    {
        return $this->latestDocumentsByType((int) $business->id)
            ->filter(fn (VerificationDocument $document): bool => $document->status === VerificationDocumentStatus::Rejected)
            ->count();
    }

    /**
     * @return array{can_approve: bool, reason: ?string}
     */
    public function canApproveVerification(BusinessInfo $business): array
    {
        try {
            $this->assertCanApproveVerification($business);

            return ['can_approve' => true, 'reason' => null];
        } catch (RuntimeException $exception) {
            return ['can_approve' => false, 'reason' => $exception->getMessage()];
        }
    }

    /**
     * @throws RuntimeException
     */
    public function assertCanApproveVerification(BusinessInfo $business): void
    {
        $requiredTypes = [
            'business_registration',
            'identity_proof',
            'address_proof',
        ];

        $latestByType = $this->latestDocumentsByType((int) $business->id);

        if ($latestByType->isEmpty()) {
            throw new RuntimeException('No verification documents have been uploaded yet.');
        }

        $missingTypes = array_values(array_filter(
            $requiredTypes,
            fn (string $type): bool => ! $latestByType->has($type),
        ));

        if ($missingTypes !== []) {
            throw new RuntimeException(
                'Cannot approve verification until all required documents are uploaded: '
                .implode(', ', array_map(fn (string $type): string => str_replace('_', ' ', $type), $missingTypes))
                .'.',
            );
        }

        $rejectedLatest = $latestByType->filter(
            fn (VerificationDocument $document): bool => $document->status === VerificationDocumentStatus::Rejected,
        );

        if ($rejectedLatest->isNotEmpty()) {
            $labels = $rejectedLatest
                ->map(fn (VerificationDocument $document): string => str_replace('_', ' ', (string) $document->document_type))
                ->values()
                ->all();

            throw new RuntimeException(
                'Cannot approve verification while rejected documents remain ('.implode(', ', $labels).'). Ask the vendor to upload replacements first.',
            );
        }

        $blockingLatest = $latestByType->filter(
            fn (VerificationDocument $document): bool => $document->status !== VerificationDocumentStatus::Approved
                && $document->status !== VerificationDocumentStatus::Pending,
        );

        if ($blockingLatest->isNotEmpty()) {
            throw new RuntimeException('Cannot approve verification until every required document is approved or pending review.');
        }

        if ($business->verification_status === VerificationStatus::Approved) {
            if ($this->countPendingDocuments($business) === 0) {
                throw new RuntimeException('All documents are already approved.');
            }

            return;
        }

        if ($business->verification_status === VerificationStatus::None) {
            if (! $this->hasOpenDocumentReview($business)) {
                throw new RuntimeException('Only pending verification requests can be approved.');
            }

            return;
        }

        if ($business->verification_status !== VerificationStatus::Pending) {
            throw new RuntimeException('Only pending verification requests can be approved.');
        }
    }

    /**
     * @return \Illuminate\Support\Collection<string, VerificationDocument>
     */
    public function latestDocumentsByType(int $businessInfoId): \Illuminate\Support\Collection
    {
        return VerificationDocument::query()
            ->where('business_info_id', $businessInfoId)
            ->orderByDesc('id')
            ->get()
            ->groupBy('document_type')
            ->map(fn (\Illuminate\Support\Collection $group): VerificationDocument => $group->first());
    }

    public function approveAllPendingDocuments(BusinessInfo $business, Authenticatable $admin, ?string $note): BusinessInfo
    {
        $this->assertCanApproveVerification($business);

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
        $this->assertCanApproveVerification($business);

        return DB::transaction(function () use ($business, $admin, $note): BusinessInfo {
            $approvedDocCount = $this->bulkApprovePendingDocuments($business->id);

            $this->assertAllLatestDocumentsApproved((int) $business->id);

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

            $this->referralService->onVerificationApproved($fresh);

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

    /**
     * @throws RuntimeException
     */
    private function assertAllLatestDocumentsApproved(int $businessInfoId): void
    {
        $notApproved = $this->latestDocumentsByType($businessInfoId)
            ->filter(fn (VerificationDocument $document): bool => $document->status !== VerificationDocumentStatus::Approved);

        if ($notApproved->isNotEmpty()) {
            $labels = $notApproved
                ->map(fn (VerificationDocument $document): string => str_replace('_', ' ', (string) $document->document_type))
                ->values()
                ->all();

            throw new RuntimeException(
                'Cannot complete verification approval until all required documents are approved ('.implode(', ', $labels).' still need action).',
            );
        }
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
            throw new RuntimeException('This business is not verified.');
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

  /**
     * Admin grants free re-verification after a profile change revoked the badge.
     * Creates a completed, unconsumed verification payment so the vendor can upload documents again.
     *
     * @return array{payment: Payment, business: BusinessInfo}
     */
    public function grantReVerificationAccess(
        BusinessInfo $business,
        Authenticatable $admin,
        string $reason,
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A reason is required for free re-verification.');
        }

        if (! $this->canApply($business)) {
            throw new RuntimeException('This business is not eligible for re-verification at this time.');
        }

        if ($this->resolveConsumableVerificationPayment($business) !== null) {
            throw new RuntimeException('This business already has an unused verification payment. Use Re-approve now if documents are on file, or wait for the vendor to upload documents.');
        }

        if ($this->hasApprovedDocumentsOnFile($business) && ! $this->hasRejectedDocuments($business)) {
            throw new RuntimeException('Documents are already on file and approved. Use Re-approve now to restore the badge — do not grant another payment.');
        }

        if (! $this->hasPriorVerificationHistory($business)) {
            throw new RuntimeException('This business has no prior verification history to re-open.');
        }

        $vendor = $business->user;
        if ($vendor === null) {
            throw new RuntimeException('Business has no owner account.');
        }

        $packageId = $this->resolveLastVerificationPackageId($business) ?? 'individual';
        $package = $this->paymentService->findPackage(PaymentPurpose::Verification, $packageId);
        $packageTitle = (string) ($package['title'] ?? $packageId);

        return DB::transaction(function () use ($business, $admin, $reason, $vendor, $packageId, $packageTitle): array {
            $payment = Payment::query()->create([
                'user_id' => $vendor->id,
                'business_info_id' => $business->id,
                'purpose' => PaymentPurpose::Verification,
                'package_id' => $packageId,
                'amount' => 0,
                'currency' => config('pricing.verification.currency', 'NGN'),
                'tx_ref' => sprintf('admin_reverify_%s_%s', $vendor->id, strtolower(\Illuminate\Support\Str::random(10))),
                'gateway' => PaymentGateway::Paystack,
                'gateway_transaction_id' => 'admin_reverify_' . now()->timestamp,
                'status' => PaymentStatus::Completed,
                'paid_at' => now(),
                'is_consumed' => false,
                'metadata' => [
                    'package_title' => $packageTitle,
                    'line_item' => PaymentPurpose::Verification->value,
                    'reverification_waiver' => true,
                    'grant_reason' => $reason,
                    'granted_by_admin_id' => $admin instanceof User ? $admin->id : null,
                    'granted_at' => now()->toIso8601String(),
                ],
            ]);

            VerificationWorkflow::query()->create([
                'business_info_id' => $business->id,
                'triggered_by' => $vendor->id,
                'workflow_type' => VerificationWorkflowType::ReVerification,
                'status' => VerificationWorkflowStatus::Pending,
                'title' => 'Free Re-verification Granted',
                'description' => $reason,
            ]);

            VerificationNote::query()->create([
                'business_info_id' => $business->id,
                'added_by' => $this->noteAuthorId($admin),
                'note_type' => VerificationNoteType::AdminDecision,
                'note' => $reason,
                'is_visible_to_vendor' => true,
            ]);

            $freshBusiness = $business->fresh(['user:id,name,email']);

            if ($freshBusiness->user !== null) {
                $this->realtimeNotifications->verificationReverificationGranted(
                    vendor: $freshBusiness->user,
                    businessName: (string) $freshBusiness->business_name,
                    reason: $reason,
                );
            }

            return [
                'payment' => $payment->fresh(),
                'business' => $freshBusiness,
            ];
        });
    }

    /**
     * Restore the verified badge without a new payment when existing documents are still valid.
     */
    public function reapproveVerification(
        BusinessInfo $business,
        Authenticatable $admin,
        ?string $note = null,
    ): BusinessInfo {
        if ($business->verification_status === VerificationStatus::Approved) {
            throw new RuntimeException('This business is already verified.');
        }

        if ($business->is_flagged) {
            throw new RuntimeException('Flagged businesses must complete a new verification application.');
        }

        if ($business->verification_status !== VerificationStatus::None) {
            throw new RuntimeException('Only businesses awaiting re-verification can be re-approved.');
        }

        if ($business->verificationDocuments()->doesntExist()) {
            throw new RuntimeException('No verification documents on file. Grant free re-verification instead.');
        }

        return DB::transaction(function () use ($business, $admin, $note): BusinessInfo {
            $approvedDocCount = $this->bulkApprovePendingDocuments($business->id);

            $this->assertAllLatestDocumentsApproved((int) $business->id);

            $this->consumeUnusedVerificationPayments($business);

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
                    ? "Verification re-approved after profile update ({$approvedDocCount} document(s) approved)."
                    : 'Verification re-approved after profile update.'
                ),
                'is_visible_to_vendor' => true,
            ]);

            VerificationWorkflow::query()->create([
                'business_info_id' => $business->id,
                'triggered_by' => (int) $business->user_id,
                'workflow_type' => VerificationWorkflowType::ReVerification,
                'status' => VerificationWorkflowStatus::Completed,
                'title' => 'Verification Re-approved',
                'description' => $note ?? 'Admin re-approved verification without a new payment.',
                'completed_at' => now(),
                'completion_notes' => 'Verification re-approved by admin.',
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

    private function hasPriorVerificationHistory(BusinessInfo $business): bool
    {
        if ($business->verificationDocuments()->exists()) {
            return true;
        }

        return Payment::query()
            ->where('business_info_id', $business->id)
            ->where('purpose', PaymentPurpose::Verification)
            ->where('status', PaymentStatus::Completed)
            ->exists();
    }

    private function resolveLastVerificationPackageId(BusinessInfo $business): ?string
    {
        $payment = Payment::query()
            ->where('business_info_id', $business->id)
            ->where('purpose', PaymentPurpose::Verification)
            ->where('status', PaymentStatus::Completed)
            ->latest('paid_at')
            ->first();

        return $payment?->package_id;
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
            return 'Verified';
        }

        if ($business->is_flagged) {
            return 'Flagged';
        }

        if ($business->verification_status === VerificationStatus::Pending) {
            return 'Under review';
        }

        if ($this->needsVendorDocumentAction($business)) {
            return 'Action required on documents';
        }

        if ($this->hasOpenDocumentReview($business) && $this->countPendingDocuments($business) > 0) {
            return 'Under review';
        }

        if ($this->resolveConsumableVerificationPayment($business) !== null) {
            return 'Ready to submit documents';
        }

        if ($this->needsAdminReapproval($business)) {
            return 'Awaiting admin re-approval';
        }

        if ($this->hasPriorVerificationHistory($business)) {
            return 'Re-verification required';
        }

        return 'Not started';
    }

    private function consumeUnusedVerificationPayments(BusinessInfo $business): void
    {
        Payment::query()
            ->where('business_info_id', $business->id)
            ->where('purpose', PaymentPurpose::Verification)
            ->where('status', PaymentStatus::Completed)
            ->where('is_consumed', false)
            ->update(['is_consumed' => true]);
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
