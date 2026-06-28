<?php

namespace App\Services;

use App\Enums\BoostPurchaseRequestStatus;
use App\Enums\VerificationStatus;
use App\Models\BoostPurchaseRequest;
use App\Models\BusinessInfo;
use App\Models\BusinessProfileView;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendorDashboardService
{
    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
        private readonly SubscriptionService $subscriptionService,
        private readonly VerificationService $verificationService,
        private readonly ReviewReplyService $reviewReplyService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getDashboard(User $vendor, ?int $businessId = null): array
    {
        $business = $businessId !== null
            ? $this->businessInfoService->assertUserOwnsBusiness($vendor, $businessId)
            : $this->businessInfoService->findForUser($vendor);

        if ($business === null) {
            return [
                'has_business' => false,
            ];
        }

        $business->loadMissing(['category', 'location', 'subscription', 'boost']);

        $subscription = $this->subscriptionService->subscriptionPayload($business);
        $verification = $this->verificationService->getVendorVerificationStatus($business);
        $reviewStats = $this->reviewReplyService->getVendorReviewStats($vendor, $business->id);
        $profileViews = $this->profileViewMetrics($business->id);
        $enquiries = $this->enquiryMetrics($business->id, $vendor->id);
        $profileCompletion = $this->profileCompletion($business);
        $trust = $this->trustMetrics($business, $subscription, $verification, $reviewStats, $profileCompletion['percent']);

        return [
            'has_business' => true,
            'business' => $this->businessPayload($business),
            'subscription' => $subscription,
            'verification' => $this->verificationCardPayload($business, $verification),
            'boost' => $this->boostPayload($business),
            'stats' => [
                'profile_views' => $profileViews['total'],
                'profile_views_delta_percent' => $profileViews['delta_percent'],
                'enquiries' => $enquiries['total'],
                'enquiries_delta_percent' => $enquiries['delta_percent'],
                'enquiries_progress_percent' => $this->enquiriesProgressPercent(
                    $enquiries['this_week'],
                    $profileViews['this_week'],
                ),
                'average_rating' => (float) ($reviewStats['average_rating'] ?? 0),
                'total_reviews' => (int) ($reviewStats['total_reviews'] ?? 0),
                'conversion_rate' => $this->conversionRate($enquiries['total'], $profileViews['total']),
                'visibility_delta_percent' => $profileViews['delta_percent'],
                'trust_score' => $trust['score'],
                'profile_strength' => $trust['profile_strength'],
                'vendor_tier' => $trust['vendor_tier'],
            ],
            'interactions' => $this->interactionsPayload($business, $enquiries['total'], $profileViews['total']),
            'weekly_engagement' => $this->weeklyEngagement($business->id, $vendor->id),
            'profile_completion' => $profileCompletion,
            'checklist' => $this->accountChecklist($business, $subscription, $verification),
            'recent_activity' => $this->recentActivity($business, $vendor),
            'support' => [
                'avg_response_label' => 'Avg response: 2 hours',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function businessPayload(BusinessInfo $business): array
    {
        $coverPaths = is_array($business->cover_photo_paths) ? $business->cover_photo_paths : [];

        return [
            'id' => $business->id,
            'name' => $business->business_name,
            'logo_url' => public_media_url($business->logo_path),
            'cover_photo_urls' => collect($coverPaths)
                ->filter(fn($path) => is_string($path) && $path !== '')
                ->map(fn(string $path) => public_media_url($path, null))
                ->filter()
                ->values()
                ->all(),
            'has_phone' => filled($business->phone),
            'has_whatsapp' => filled($business->whatsapp),
            'has_website' => filled($business->website),
        ];
    }

    /**
     * @param  array<string, mixed>  $verification
     * @return array<string, mixed>
     */
    private function verificationCardPayload(BusinessInfo $business, array $verification): array
    {
        $status = $business->verification_status;
        $badgeTone = match ($status) {
            VerificationStatus::Approved => 'emerald',
            VerificationStatus::Pending => 'amber',
            default => 'muted',
        };

        $description = match ($status) {
            VerificationStatus::Approved => 'Your business is verified. Customers will see the verified badge on your profile.',
            VerificationStatus::Pending => 'Our curation team is currently reviewing your licensing and identity documents. This typically takes 24-48 hours.',
            default => 'Complete verification to build trust and unlock the verified badge on your public profile.',
        };

        return [
            'status' => $status->value,
            'status_label' => (string) ($verification['verification_status_label'] ?? $status->label()),
            'badge_tone' => $badgeTone,
            'description' => $description,
            'is_verified' => $this->verificationService->showsVerifiedBadge($business),
        ];
    }

    /**
     * @return array{status: string, status_label: string}
     */
    private function boostPayload(BusinessInfo $business): array
    {
        $hasActive = BoostPurchaseRequest::query()
            ->where('business_info_id', $business->id)
            ->where('status', BoostPurchaseRequestStatus::Approved)
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->exists();

        if ($hasActive) {
            return [
                'status' => 'active',
                'status_label' => 'Active',
            ];
        }

        $hasPending = BoostPurchaseRequest::query()
            ->where('business_info_id', $business->id)
            ->where('status', BoostPurchaseRequestStatus::PendingAdmin)
            ->exists();

        if ($hasPending) {
            return [
                'status' => 'pending',
                'status_label' => 'Pending approval',
            ];
        }

        return [
            'status' => 'inactive',
            'status_label' => 'Inactive',
        ];
    }

    /**
     * @return array{total: int, this_week: int, delta_percent: float|null}
     */
    private function profileViewMetrics(int $businessInfoId): array
    {
        if (! Schema::hasTable('business_profile_views')) {
            return ['total' => 0, 'this_week' => 0, 'delta_percent' => null];
        }

        $base = BusinessProfileView::query()->where('business_info_id', $businessInfoId);
        $total = (int) (clone $base)->count();
        $thisWeek = (int) (clone $base)
            ->where('viewed_at', '>=', now()->startOfWeek())
            ->count();
        $lastWeek = (int) (clone $base)
            ->whereBetween('viewed_at', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek(),
            ])
            ->count();

        return [
            'total' => $total,
            'this_week' => $thisWeek,
            'delta_percent' => $this->percentDelta($thisWeek, $lastWeek),
        ];
    }

    /**
     * @return array{total: int, this_week: int, delta_percent: float|null}
     */
    private function enquiryMetrics(int $businessInfoId, int $vendorUserId): array
    {
        if (! Schema::hasTable('messages') || ! Schema::hasTable('conversations')) {
            return ['total' => 0, 'this_week' => 0, 'delta_percent' => null];
        }

        $total = $this->countEnquiries($businessInfoId, $vendorUserId);
        $thisWeek = $this->countEnquiries($businessInfoId, $vendorUserId, now()->startOfWeek());
        $lastWeek = $this->countEnquiries(
            $businessInfoId,
            $vendorUserId,
            now()->subWeek()->startOfWeek(),
            now()->subWeek()->endOfWeek(),
        );

        return [
            'total' => $total,
            'this_week' => $thisWeek,
            'delta_percent' => $this->percentDelta($thisWeek, $lastWeek),
        ];
    }

    private function countEnquiries(
        int $businessInfoId,
        int $vendorUserId,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): int {
        $query = DB::table('messages')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('conversation_participants', function ($join) use ($vendorUserId): void {
                $join->on('conversation_participants.conversation_id', '=', 'conversations.id')
                    ->where('conversation_participants.user_id', '=', $vendorUserId);
            })
            ->where('conversations.business_info_id', $businessInfoId)
            ->where('messages.sender_id', '!=', $vendorUserId)
            ->whereNull('messages.deleted_at')
            ->whereNull('conversations.deleted_at');

        if ($from !== null) {
            $query->where('messages.created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('messages.created_at', '<=', $to);
        }

        return (int) $query->distinct()->count('conversations.id');
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    private function interactionsPayload(BusinessInfo $business, int $enquiries, int $profileViews): array
    {
        return [
            [
                'key' => 'calls',
                'label' => 'Calls',
                'count' => filled($business->phone) ? (int) round($profileViews * 0.12) : 0,
            ],
            [
                'key' => 'whatsapp',
                'label' => 'WhatsApp',
                'count' => filled($business->whatsapp) ? $enquiries : 0,
            ],
            [
                'key' => 'website',
                'label' => 'Website',
                'count' => filled($business->website) ? (int) round($profileViews * 0.2) : 0,
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, views: list<int>, interactions: list<int>, views_heights: list<int>, interactions_heights: list<int>}
     */
    private function weeklyEngagement(int $businessInfoId, int $vendorUserId): array
    {
        $labels = [];
        $rawViews = [];
        $rawInteractions = [];

        $start = Carbon::now()->startOfDay()->subDays(6);

        for ($i = 0; $i < 7; $i++) {
            $dayStart = $start->copy()->addDays($i);
            $dayEnd = $dayStart->copy()->endOfDay();
            $labels[] = $dayStart->format('D');

            $rawViews[] = Schema::hasTable('business_profile_views')
                ? (int) BusinessProfileView::query()
                    ->where('business_info_id', $businessInfoId)
                    ->whereBetween('viewed_at', [$dayStart, $dayEnd])
                    ->count()
                : 0;

            $rawInteractions[] = $this->countEnquiries($businessInfoId, $vendorUserId, $dayStart, $dayEnd);
        }

        $max = max(1, ...$rawViews, ...$rawInteractions);

        return [
            'labels' => $labels,
            'views' => $rawViews,
            'interactions' => $rawInteractions,
            'views_heights' => array_map(
                fn(int $value): int => (int) round(($value / $max) * 100),
                $rawViews,
            ),
            'interactions_heights' => array_map(
                fn(int $value): int => (int) round(($value / $max) * 100),
                $rawInteractions,
            ),
        ];
    }

    /**
     * @return array{percent: int, items: list<array{key: string, label: string, done: bool}>, next_step_key: string|null, next_step_label: string|null}
     */
    private function profileCompletion(BusinessInfo $business): array
    {
        $items = [
            [
                'key' => 'business_info',
                'label' => 'Business Info',
                'done' => filled($business->business_name)
                    && filled($business->business_description)
                    && $business->category_id !== null
                    && $business->location_id !== null,
            ],
            [
                'key' => 'verified_id',
                'label' => 'Verified ID',
                'done' => $business->verification_status === VerificationStatus::Approved,
            ],
            [
                'key' => 'profile_photo',
                'label' => 'Profile Photo',
                'done' => filled($business->logo_path),
            ],
        ];

        $doneCount = collect($items)->where('done', true)->count();
        $percent = (int) round(($doneCount / max(1, count($items))) * 100);

        $next = collect($items)->first(fn(array $item): bool => ! $item['done']);

        return [
            'percent' => $percent,
            'items' => $items,
            'next_step_key' => $next['key'] ?? null,
            'next_step_label' => $next['label'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $subscription
     * @param  array<string, mixed>  $verification
     * @return list<array{label: string, done: bool}>
     */
    private function accountChecklist(BusinessInfo $business, array $subscription, array $verification): array
    {
        return [
            [
                'label' => 'Verified business license',
                'done' => (bool) ($verification['is_approved'] ?? false),
            ],
            [
                'label' => 'Premium subscription active',
                'done' => (bool) ($subscription['is_premium_active'] ?? false),
            ],
            [
                'label' => 'Portfolio photos uploaded',
                'done' => is_array($business->cover_photo_paths) && count($business->cover_photo_paths) > 0,
            ],
            [
                'label' => 'Business contact details added',
                'done' => filled($business->phone) || filled($business->whatsapp),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $reviewStats
     * @return array{score: int, profile_strength: string, vendor_tier: string}
     */
    private function trustMetrics(
        BusinessInfo $business,
        array $subscription,
        array $verification,
        array $reviewStats,
        int $completionPercent,
    ): array {
        $score = 40;
        $score += (int) round($completionPercent * 0.3);
        if ((bool) ($verification['is_approved'] ?? false)) {
            $score += 20;
        }
        if ((bool) ($subscription['is_premium_active'] ?? false)) {
            $score += 10;
        }
        $score += min(10, (int) round(((float) ($reviewStats['average_rating'] ?? 0)) * 2));

        $score = min(100, max(0, $score));

        $profileStrength = match (true) {
            $completionPercent >= 80 => 'High',
            $completionPercent >= 50 => 'Medium',
            default => 'Low',
        };

        $vendorTier = (bool) ($subscription['is_premium_active'] ?? false)
            ? 'Premium'
            : ((string) ($subscription['plan_label'] ?? 'Free'));

        return [
            'score' => $score,
            'profile_strength' => $profileStrength,
            'vendor_tier' => $vendorTier,
        ];
    }

    /**
     * @return list<array{title: string, subtitle: string, dot: string}>
     */
    private function recentActivity(BusinessInfo $business, User $vendor): array
    {
        $items = [];

        if (Schema::hasTable('business_profile_views')) {
            $views = BusinessProfileView::query()
                ->where('business_info_id', $business->id)
                ->latest('viewed_at')
                ->limit(3)
                ->get();

            foreach ($views as $view) {
                $items[] = [
                    'title' => 'Profile viewed by a potential buyer',
                    'subtitle' => $view->viewed_at ? $view->viewed_at->diffForHumans() : 'Recently',
                    'dot' => 'primary',
                    'sort_at' => $view->viewed_at?->timestamp ?? 0,
                ];
            }
        }

        $reviews = Review::query()
            ->where('business_id', $business->id)
            ->latest()
            ->limit(2)
            ->get();

        foreach ($reviews as $review) {
            $items[] = [
                'title' => sprintf('%d-star review published on your profile', (int) $review->rating),
                'subtitle' => $review->created_at?->diffForHumans() ?? 'Recently',
                'dot' => 'brand-red',
                'sort_at' => $review->created_at?->timestamp ?? 0,
            ];
        }

        $expiringBoost = BoostPurchaseRequest::query()
            ->where('business_info_id', $business->id)
            ->where('status', BoostPurchaseRequestStatus::Approved)
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->addHours(48)])
            ->orderBy('ends_at')
            ->first();

        if ($expiringBoost !== null) {
            $items[] = [
                'title' => 'Boost expiring soon — renew to stay on top',
                'subtitle' => $expiringBoost->ends_at?->diffForHumans() ?? 'Soon',
                'dot' => 'brand-red',
                'sort_at' => $expiringBoost->ends_at?->timestamp ?? now()->timestamp,
            ];
        }

        if (Schema::hasTable('messages')) {
            $latestMessageAt = DB::table('messages')
                ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
                ->join('conversation_participants', function ($join) use ($vendor): void {
                    $join->on('conversation_participants.conversation_id', '=', 'conversations.id')
                        ->where('conversation_participants.user_id', '=', $vendor->id);
                })
                ->where('messages.sender_id', '!=', $vendor->id)
                ->whereNull('messages.deleted_at')
                ->max('messages.created_at');

            if ($latestMessageAt !== null) {
                $items[] = [
                    'title' => 'New enquiry from a customer',
                    'subtitle' => Carbon::parse($latestMessageAt)->diffForHumans(),
                    'dot' => 'brand-red',
                    'sort_at' => Carbon::parse($latestMessageAt)->timestamp,
                ];
            }
        }

        usort($items, fn(array $a, array $b): int => ($b['sort_at'] ?? 0) <=> ($a['sort_at'] ?? 0));

        return array_values(array_map(
            fn(array $item): array => [
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
                'dot' => $item['dot'],
            ],
            array_slice($items, 0, 4),
        ));
    }

    private function conversionRate(int $enquiries, int $profileViews): float
    {
        if ($profileViews <= 0) {
            return 0.0;
        }

        return round(min(100.0, ($enquiries / $profileViews) * 100), 1);
    }

    private function enquiriesProgressPercent(int $weeklyEnquiries, int $weeklyViews): int
    {
        if ($weeklyViews <= 0) {
            return min(100, $weeklyEnquiries > 0 ? 75 : 0);
        }

        return min(100, (int) round(($weeklyEnquiries / $weeklyViews) * 100));
    }

    private function percentDelta(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
