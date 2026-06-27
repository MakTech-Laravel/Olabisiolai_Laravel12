<?php

namespace App\Http\Resources\Api\V1;

use App\Models\VerificationDocument;
use App\Models\VerificationNote;
use App\Models\VerificationWorkflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'vendor' => $this->when(
                $this->relationLoaded('user') && $this->user !== null,
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ]
            ),
            'category' => $this->when(
                $this->relationLoaded('category') && $this->category !== null,
                fn () => [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ]
            ),
            'logo_url' => $this->logo_path ? public_media_url($this->logo_path, null) : null,
            'verification_status' => $this->verification_status->value,
            'verification_status_label' => $this->verification_status->value === 'approved'
                ? $this->verification_status->label()
                : ($this->is_flagged ? 'Flagged' : $this->verification_status->label()),
            'is_flagged' => (bool) $this->is_flagged,
            'is_approved' => $this->verification_status->value === 'approved',
            'business_status' => $this->business_status->value,
            'verified_by' => $this->when(
                $this->relationLoaded('verifiedBy') && $this->verifiedBy !== null,
                fn () => [
                    'id' => $this->verifiedBy->id,
                    'name' => $this->verifiedBy->name,
                    'email' => $this->verifiedBy->email,
                ]
            ),
            'verified_at' => $this->verified_at ? humanDateTime($this->verified_at) : null,
            'submitted_at' => humanDateTime(
                $this->verification_workflows_max_created_at
                    ?? $this->verification_documents_max_created_at
                    ?? $this->updated_at,
            ),
            'verification_note' => $this->verification_note,
            'documents' => $this->when(
                $this->relationLoaded('verificationDocuments'),
                fn () => $this->verificationDocuments->map(fn (VerificationDocument $doc): array => [
                    'id' => $doc->id,
                    'parent_document_id' => $doc->parent_document_id,
                    'document_type' => $doc->document_type,
                    'title' => $doc->title,
                    'description' => $doc->description,
                    'file_name' => $doc->file_name,
                    'file_url' => $doc->file_path ? storage_url($doc->file_path) : null,
                    'mime_type' => $doc->mime_type,
                    'file_size' => $doc->file_size,
                    'status' => $doc->status->value,
                    'rejection_reason' => $doc->rejection_reason,
                    'uploaded_by' => $doc->relationLoaded('uploadedBy') && $doc->uploadedBy !== null
                        ? ['id' => $doc->uploadedBy->id, 'name' => $doc->uploadedBy->name]
                        : null,
                    'submitted_at' => humanDateTime($doc->created_at),
                ])->values()
            ),
            'notes' => $this->when(
                $this->relationLoaded('verificationNotes'),
                fn () => $this->verificationNotes->map(fn (VerificationNote $note): array => [
                    'id' => $note->id,
                    'note_type' => $note->note_type->value,
                    'note' => $note->note,
                    'is_visible_to_vendor' => $note->is_visible_to_vendor,
                    'added_by' => $note->relationLoaded('addedBy') && $note->addedBy !== null
                        ? ['id' => $note->addedBy->id, 'name' => $note->addedBy->name]
                        : null,
                    'created_at' => humanDateTime($note->created_at),
                ])->values()
            ),
            'payments' => $this->when(
                $this->relationLoaded('payments'),
                fn () => $this->payments->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'package_id' => $payment->package_id,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status->value,
                    'is_consumed' => (bool) $payment->is_consumed,
                    'paid_at' => $payment->paid_at ? humanDateTime($payment->paid_at) : null,
                ])->values()
            ),
            'workflows' => $this->when(
                $this->relationLoaded('verificationWorkflows'),
                fn () => $this->verificationWorkflows->map(fn (VerificationWorkflow $wf): array => [
                    'id' => $wf->id,
                    'workflow_type' => $wf->workflow_type->value,
                    'status' => $wf->status->value,
                    'title' => $wf->title,
                    'description' => $wf->description,
                    'completed_at' => $wf->completed_at ? humanDateTime($wf->completed_at) : null,
                    'completion_notes' => $wf->completion_notes,
                    'created_at' => humanDateTime($wf->created_at),
                ])->values()
            ),
            'created_at' => humanDateTime($this->created_at),
            'updated_at' => humanDateTime($this->updated_at),
        ];
    }
}
