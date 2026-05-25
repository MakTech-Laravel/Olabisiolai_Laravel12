<?php

namespace App\Services;

use App\Enums\BoostPurchaseRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\VerificationStatus;
use App\Models\BoostPurchaseRequest;
use App\Models\BusinessInfo;
use App\Models\BusinessProfileView;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardService
{
    /**
     * @return array{pending_verifications: int, pending_boosts: int}
     */
    public function getSidebarCounts(): array
    {
        return [
            'pending_verifications' => BusinessInfo::query()
                ->where('verification_status', VerificationStatus::Pending)
                ->count(),
            'pending_boosts' => BoostPurchaseRequest::query()
                ->where('status', BoostPurchaseRequestStatus::PendingAdmin)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboard(string $range = 'monthly'): array
    {
        $range = $range === 'weekly' ? 'weekly' : 'monthly';

        return [
            'range' => $range,
            'stats' => $this->stats(),
            'leads_over_time' => $this->leadsOverTime($range),
            'new_businesses' => $this->newBusinessesSeries($range),
            'quick_actions' => $this->quickActions(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        return [
            'total_businesses' => BusinessInfo::query()->count(),
            'verified_businesses' => BusinessInfo::query()
                ->where('verification_status', VerificationStatus::Approved)
                ->count(),
            'pending_verifications' => BusinessInfo::query()
                ->where('verification_status', VerificationStatus::Pending)
                ->count(),
            'daily_active_users' => $this->dailyActiveUsers(),
            'total_lead_clicks' => $this->totalLeadClicks(),
        ];
    }

    private function dailyActiveUsers(): int
    {
        $since = now()->startOfDay();
        $userIds = collect();

        if (Schema::hasTable('oauth_access_tokens')) {
            $userIds = $userIds->merge(
                DB::table('oauth_access_tokens')
                    ->where('revoked', false)
                    ->where('updated_at', '>=', $since)
                    ->whereNotNull('user_id')
                    ->pluck('user_id'),
            );
        }

        if (Schema::hasTable('sessions')) {
            $userIds = $userIds->merge(
                DB::table('sessions')
                    ->where('last_activity', '>=', $since->timestamp)
                    ->whereNotNull('user_id')
                    ->pluck('user_id'),
            );
        }

        $viewersToday = BusinessProfileView::query()
            ->where('viewed_at', '>=', $since)
            ->whereNotNull('viewer_user_id')
            ->distinct()
            ->pluck('viewer_user_id');

        return $userIds->merge($viewersToday)->unique()->filter()->count();
    }

    private function totalLeadClicks(): int
    {
        if (! Schema::hasTable('business_profile_views')) {
            return 0;
        }

        return BusinessProfileView::query()->count();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function leadsOverTime(string $range): array
    {
        if (! Schema::hasTable('business_profile_views')) {
            return $this->emptySeries($range);
        }

        if ($range === 'weekly') {
            return $this->dailyCountSeries(
                BusinessProfileView::query(),
                'viewed_at',
            );
        }

        return $this->monthlyCountSeries(
            BusinessProfileView::query(),
            'viewed_at',
        );
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function newBusinessesSeries(string $range): array
    {
        if ($range === 'weekly') {
            return $this->dailyCountSeries(
                BusinessInfo::query(),
                'created_at',
            );
        }

        return $this->monthlyCountSeries(
            BusinessInfo::query(),
            'created_at',
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return list<array{label: string, value: int}>
     */
    private function dailyCountSeries($query, string $column): array
    {
        $points = [];
        $start = Carbon::now()->startOfDay()->subDays(6);

        for ($i = 0; $i < 7; $i++) {
            $dayStart = $start->copy()->addDays($i);
            $dayEnd = $dayStart->copy()->endOfDay();
            $points[] = [
                'label' => $dayStart->format('D'),
                'value' => (int) (clone $query)
                    ->whereBetween($column, [$dayStart, $dayEnd])
                    ->count(),
            ];
        }

        return $points;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return list<array{label: string, value: int}>
     */
    private function monthlyCountSeries($query, string $column): array
    {
        $points = [];
        $start = Carbon::now()->startOfMonth()->subMonths(5);

        for ($i = 0; $i < 6; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $points[] = [
                'label' => $monthStart->format('M'),
                'value' => (int) (clone $query)
                    ->whereBetween($column, [$monthStart, $monthEnd])
                    ->count(),
            ];
        }

        return $points;
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function emptySeries(string $range): array
    {
        if ($range === 'weekly') {
            $start = Carbon::now()->startOfDay()->subDays(6);
            $points = [];
            for ($i = 0; $i < 7; $i++) {
                $points[] = [
                    'label' => $start->copy()->addDays($i)->format('D'),
                    'value' => 0,
                ];
            }

            return $points;
        }

        $start = Carbon::now()->startOfMonth()->subMonths(5);
        $points = [];
        for ($i = 0; $i < 6; $i++) {
            $points[] = [
                'label' => $start->copy()->addMonths($i)->format('M'),
                'value' => 0,
            ];
        }

        return $points;
    }

    /**
     * @return list<array{title: string, description: string, action: string, href: string}>
     */
    private function quickActions(): array
    {
        $pendingVerifications = BusinessInfo::query()
            ->where('verification_status', VerificationStatus::Pending)
            ->count();

        $pendingPayments = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->count();

        $boostQueue = BoostPurchaseRequest::query()
            ->where('status', BoostPurchaseRequestStatus::PendingAdmin)
            ->count();

        return [
            [
                'title' => 'Approve Pending Businesses',
                'description' => $pendingVerifications === 1
                    ? '1 business waiting for approval'
                    : "{$pendingVerifications} businesses waiting for approval",
                'action' => 'Review',
                'href' => '/admin/verifications',
            ],
            [
                'title' => 'Confirm Payments',
                'description' => $pendingPayments === 1
                    ? '1 payment pending confirmation'
                    : "{$pendingPayments} payments pending confirmation",
                'action' => 'View',
                'href' => '/admin/payments',
            ],
            [
                'title' => 'Assign Boost Slots',
                'description' => $boostQueue === 1
                    ? '1 business in queue'
                    : "{$boostQueue} businesses in queue",
                'action' => 'Manage',
                'href' => '/admin/boost-system',
            ],
        ];
    }
}
