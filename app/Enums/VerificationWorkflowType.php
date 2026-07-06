<?php

namespace App\Enums;

enum VerificationWorkflowType: string
{
    case InitialSubmission = 'initial_submission';
    case ReVerification = 're_verification';
    case DocumentReview = 'document_review';
    case BackgroundCheck = 'background_check';
    case SiteVisit = 'site_visit';
    case FinalApproval = 'final_approval';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    public function label(): string
    {
        return match ($this) {
            self::InitialSubmission => 'Initial Submission',
            self::ReVerification => 'Re-verification',
            self::DocumentReview => 'Document Review',
            self::BackgroundCheck => 'Background Check',
            self::SiteVisit => 'Site Visit',
            self::FinalApproval => 'Final Approval',
        };
    }
}
