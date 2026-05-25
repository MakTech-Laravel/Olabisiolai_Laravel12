<?php

namespace App\Services;

use App\Enums\ReviewReportReason;
use App\Enums\ReviewReportStatus;
use App\Models\BusinessInfo;
use App\Models\BusinessReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;

class BusinessReportService
{
    /**
     * @throws \RuntimeException
     */
    public function storeReport(BusinessInfo $business, User $user, array $data): BusinessReport
    {
        $reason = ReviewReportReason::from($data['reason']);

        try {
            $report = BusinessReport::create([
                'business_info_id' => $business->id,
                'user_id' => $user->id,
                'reason' => $reason,
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
                'status' => ReviewReportStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new \RuntimeException('You have already reported this business.');
        }

        return $report->load([
            'business:id,business_name',
            'user:id,first_name,last_name,email',
        ]);
    }

    /**
     * Paginated list of business reports for admin moderation.
     */
    public function getReports(array $filters = []): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;

        $query = BusinessReport::with([
            'business:id,business_name',
            'user:id,first_name,last_name,email',
        ])->latest('created_at');

        if (isset($filters['status'])) {
            $query->where('status', ReviewReportStatus::from($filters['status']));
        }

        if (isset($filters['reason'])) {
            $query->where('reason', ReviewReportReason::from($filters['reason']));
        }

        if (isset($filters['business_info_id'])) {
            $query->where('business_info_id', $filters['business_info_id']);
        }

        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $search = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('business', fn($b) => $b->where('business_name', 'like', $search))
                    ->orWhereHas('user', fn($u) => $u
                        ->where('email', 'like', $search)
                        ->orWhere('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search));
            });
        }

        return $query->paginate($perPage);
    }

    public function dismissReport(BusinessReport $report): BusinessReport
    {
        $report->update([
            'status' => ReviewReportStatus::Dismissed,
            'reviewed_at' => now(),
        ]);

        return $report->fresh(['business', 'user']);
    }

    public function resolveReport(BusinessReport $report): BusinessReport
    {
        $report->update([
            'status' => ReviewReportStatus::Reviewed,
            'reviewed_at' => now(),
        ]);

        return $report->fresh(['business', 'user']);
    }

    public function pendingCount(): int
    {
        return BusinessReport::pending()->count();
    }
}
