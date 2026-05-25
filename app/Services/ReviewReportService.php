<?php

namespace App\Services;

use App\Enums\ReviewReportReason;
use App\Enums\ReviewReportStatus;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;

class ReviewReportService
{
    /**
     * Submit a report for a review. One report per user per review.
     *
     * @throws \RuntimeException if the user has already reported this review
     */
    public function storeReport(Review $review, User $user, array $data): ReviewReport
    {
        $reason = ReviewReportReason::from($data['reason']);

        try {
            $report = ReviewReport::create([
                'review_id' => $review->id,
                'user_id' => $user->id,
                'reason' => $reason,
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'status' => ReviewReportStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new \RuntimeException('You have already reported this review.');
        }

        return $report->load(['review:id,review_text,rating,full_name,is_anonymous', 'user:id,first_name,last_name,email']);
    }

    /**
     * Paginated list of all review reports for admin.
     */
    public function getReports(array $filters = []): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;

        $query = ReviewReport::with([
            'review:id,review_text,rating,full_name,is_anonymous,business_id,is_approved',
            'review.business:id,business_name',
            'user:id,first_name,last_name,email',
        ])->latest('created_at');

        if (isset($filters['status'])) {
            $query->where('status', ReviewReportStatus::from($filters['status']));
        }

        if (isset($filters['reason'])) {
            $query->where('reason', ReviewReportReason::from($filters['reason']));
        }

        if (isset($filters['review_id'])) {
            $query->where('review_id', $filters['review_id']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Mark a report as dismissed (no action needed).
     */
    public function dismissReport(ReviewReport $report): ReviewReport
    {
        $report->update([
            'status' => ReviewReportStatus::Dismissed,
            'reviewed_at' => now(),
        ]);

        return $report->fresh(['review', 'user']);
    }

    /**
     * Mark a report as reviewed (action taken on the review).
     */
    public function resolveReport(ReviewReport $report): ReviewReport
    {
        $report->update([
            'status' => ReviewReportStatus::Reviewed,
            'reviewed_at' => now(),
        ]);

        return $report->fresh(['review', 'user']);
    }

    /**
     * Count of pending reports for dashboard/sidebar.
     */
    public function pendingCount(): int
    {
        return ReviewReport::pending()->count();
    }
}
