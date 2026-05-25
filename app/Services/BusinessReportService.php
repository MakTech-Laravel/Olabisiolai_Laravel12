<?php

namespace App\Services;

use App\Enums\ReviewReportReason;
use App\Enums\ReviewReportStatus;
use App\Models\BusinessInfo;
use App\Models\BusinessReport;
use App\Models\User;
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
}
