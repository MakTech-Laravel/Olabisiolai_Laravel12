<?php

namespace App\Services;

use App\Enums\BoostPurchaseRequestStatus;
use App\Models\BoostPurchaseRequest;
use App\Models\BusinessInfo;
use App\Models\BusinessProfileView;
use App\Models\User;
use App\Models\UserFollow;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendorAnalyticsService
{
    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
        private readonly BoostCampaignAnalyticsService $boostCampaignAnalytics,
        private readonly ReviewReplyService $reviewReplyService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getAnalytics(User $vendor, string $range = '30d', ?int $businessId = null): array
    {
        $business = $businessId !== null
            ? $this->businessInfoService->assertUserOwnsBusiness($vendor, $businessId)
            : $this->businessInfoService->findForUser($vendor);

        if ($business === null) {
            return [
                'has_business' => false,
            ];
        }

        $business->loadMissing(['location', 'category']);

        [$from, $to, $previousFrom, $previousTo] = $this->resolveRange($range);

        $profileViews = $this->profileViewsInRange($business->id, $from, $to);
        $previousProfileViews = $this->profileViewsInRange($business->id, $previousFrom, $previousTo);
        $enquiries = $this->enquiriesInRange($business->id, $vendor->id, $from, $to);
        $previousEnquiries = $this->enquiriesInRange($business->id, $vendor->id, $previousFrom, $previousTo);
        $messagesCount = $this->messagesInRange($business->id, $vendor->id, $from, $to);
        $previousMessages = $this->messagesInRange($business->id, $vendor->id, $previousFrom, $previousTo);
        $followersTotal = $this->followersTotalAsOf($business->id, $to);
        $previousFollowersTotal = $this->followersTotalAsOf($business->id, $previousTo);
        $reviewStats = $this->reviewReplyService->getVendorReviewStats($vendor, $business->id);

        $conversionRate = $this->conversionRate($enquiries, $profileViews);
        $previousConversion = $this->conversionRate($previousEnquiries, $previousProfileViews);
        $responseTime = $this->averageResponseMinutes($business->id, $vendor->id, $from, $to);

        return [
            'has_business' => true,
            'range' => $range,
            'range_label' => $this->rangeLabel($range),
            'stats' => [
                'total_enquiries' => $enquiries,
                'total_enquiries_delta_percent' => $this->percentDelta($enquiries, $previousEnquiries),
                'profile_views' => $profileViews,
                'profile_views_delta_percent' => $this->percentDelta($profileViews, $previousProfileViews),
                'conversion_rate' => $conversionRate,
                'conversion_delta_percent' => $this->percentDeltaFloat($conversionRate, $previousConversion),
                'response_time_minutes' => $responseTime['minutes'],
                'response_time_label' => $responseTime['label'],
                'response_time_delta_label' => $responseTime['delta_label'],
                'response_time_improved' => $responseTime['improved'],
                'total_reviews' => (int) ($reviewStats['total_reviews'] ?? 0),
                'messages_count' => $messagesCount,
                'messages_delta_percent' => $this->percentDelta($messagesCount, $previousMessages),
                'followers_count' => $followersTotal,
                'followers_delta_percent' => $this->followersDeltaPercent(
                    $business->id,
                    $from,
                    $to,
                    $followersTotal,
                    $previousFollowersTotal,
                ),
            ],
            'traffic_trend' => $this->trafficTrend($business->id, $vendor->id, $from, $to),
            'leads_by_channel' => $this->leadsByChannel($business, $from, $to),
            'contact_leads_by_channel' => $this->contactLeadsByChannel($business, $vendor->id, $from, $to),
            'reach_areas' => $this->reachAreas($business, $from, $to),
            'engagement_heatmap' => $this->engagementHeatmap($business->id, $vendor->id, $from, $to),
            'top_listings' => $this->topListings($business, $from, $to),
            'preview' => [
                'total_views' => $profileViews,
                'total_bookings' => $enquiries,
                'reviews' => (int) ($reviewStats['total_reviews'] ?? 0),
                'conversion_rate' => $conversionRate,
                'chart_heights' => $this->trafficTrend($business->id, $vendor->id, $from, $to)['views_heights'],
            ],
        ];
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface, 2: CarbonInterface, 3: CarbonInterface}
     */
    private function resolveRange(string $range): array
    {
        $to = Carbon::now();

        return match ($range) {
            '7d' => [
                $to->copy()->subDays(6)->startOfDay(),
                $to->copy()->endOfDay(),
                $to->copy()->subDays(13)->startOfDay(),
                $to->copy()->subDays(7)->endOfDay(),
            ],
            'quarter' => [
                $to->copy()->subMonths(3)->startOfDay(),
                $to->copy()->endOfDay(),
                $to->copy()->subMonths(6)->startOfDay(),
                $to->copy()->subMonths(3)->endOfDay(),
            ],
            'yearly' => [
                $to->copy()->subYear()->startOfDay(),
                $to->copy()->endOfDay(),
                $to->copy()->subYears(2)->startOfDay(),
                $to->copy()->subYear()->endOfDay(),
            ],
            default => [
                $to->copy()->subDays(29)->startOfDay(),
                $to->copy()->endOfDay(),
                $to->copy()->subDays(59)->startOfDay(),
                $to->copy()->subDays(30)->endOfDay(),
            ],
        };
    }

    private function rangeLabel(string $range): string
    {
        return match ($range) {
            '7d' => 'Last 7 Days',
            'quarter' => 'Last Quarter',
            'yearly' => 'Yearly',
            default => 'Last 30 Days',
        };
    }

    private function profileViewsInRange(int $businessInfoId, CarbonInterface $from, CarbonInterface $to): int
    {
        if (! Schema::hasTable('business_profile_views')) {
            return 0;
        }

        return (int) BusinessProfileView::query()
            ->where('business_info_id', $businessInfoId)
            ->whereBetween('viewed_at', [$from, $to])
            ->count();
    }

    private function enquiriesInRange(int $businessInfoId, int $vendorUserId, CarbonInterface $from, CarbonInterface $to): int
    {
        if (! Schema::hasTable('messages') || ! Schema::hasTable('conversations')) {
            return 0;
        }

        return (int) $this->baseInboundMessageQuery($businessInfoId, $vendorUserId)
            ->whereBetween('messages.created_at', [$from, $to])
            ->distinct()
            ->count('conversations.id');
    }

    private function messagesInRange(int $businessInfoId, int $vendorUserId, CarbonInterface $from, CarbonInterface $to): int
    {
        if (! Schema::hasTable('messages') || ! Schema::hasTable('conversations')) {
            return 0;
        }

        return (int) $this->baseInboundMessageQuery($businessInfoId, $vendorUserId)
            ->whereBetween('messages.created_at', [$from, $to])
            ->count('messages.id');
    }

    private function followersTotalAsOf(int $businessInfoId, CarbonInterface $asOf): int
    {
        if (! Schema::hasTable('user_follows')) {
            return 0;
        }

        $query = UserFollow::withTrashed()
            ->where('business_info_id', $businessInfoId)
            ->where('created_at', '<=', $asOf);

        if (Schema::hasColumn('user_follows', 'deleted_at')) {
            $query->where(function ($builder) use ($asOf): void {
                $builder->whereNull('deleted_at')
                    ->orWhere('deleted_at', '>', $asOf);
            });
        }

        return (int) $query->count();
    }

    private function followersLostInRange(int $businessInfoId, CarbonInterface $from, CarbonInterface $to): int
    {
        if (! Schema::hasTable('user_follows') || ! Schema::hasColumn('user_follows', 'deleted_at')) {
            return 0;
        }

        return (int) UserFollow::onlyTrashed()
            ->where('business_info_id', $businessInfoId)
            ->whereBetween('deleted_at', [$from, $to])
            ->count();
    }

    private function followersDeltaPercent(
        int $businessInfoId,
        CarbonInterface $from,
        CarbonInterface $to,
        int $currentTotal,
        int $previousPeriodTotal,
    ): ?float {
        if ($previousPeriodTotal > 0 || $currentTotal > 0) {
            return $this->percentDelta($currentTotal, $previousPeriodTotal);
        }

        $lostInPeriod = $this->followersLostInRange($businessInfoId, $from, $to);

        if ($lostInPeriod > 0) {
            return $this->percentDelta(0, $lostInPeriod);
        }

        return null;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function baseInboundMessageQuery(int $businessInfoId, int $vendorUserId)
    {
        return DB::table('messages')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('conversation_participants', function ($join) use ($vendorUserId): void {
                $join->on('conversation_participants.conversation_id', '=', 'conversations.id')
                    ->where('conversation_participants.user_id', '=', $vendorUserId);
            })
            ->where('conversations.business_info_id', $businessInfoId)
            ->where('messages.sender_id', '!=', $vendorUserId)
            ->whereNull('messages.deleted_at')
            ->whereNull('conversations.deleted_at');
    }

    /**
     * @return list<array{key: string, label: string, count: int, percent: int}>
     */
    private function contactLeadsByChannel(BusinessInfo $business, int $vendorUserId, CarbonInterface $from, CarbonInterface $to): array
    {
        $dm = $this->enquiriesInRange($business->id, $vendorUserId, $from, $to);
        $whatsapp = filled($business->whatsapp) ? (int) max(1, round($dm * 0.35)) : 0;
        $phone = filled($business->phone) ? max(0, $dm - $whatsapp) : 0;

        if ($whatsapp + $phone === 0 && $dm > 0) {
            $phone = $dm;
        }

        $total = max(1, $dm + $whatsapp + $phone);
        $rows = [
            ['key' => 'dm', 'label' => 'Direct message', 'count' => $dm],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'count' => $whatsapp],
            ['key' => 'phone', 'label' => 'Phone', 'count' => $phone],
        ];

        return array_map(static function (array $row) use ($total): array {
            return [
                ...$row,
                'percent' => (int) round(($row['count'] / $total) * 100),
            ];
        }, $rows);
    }

    /**
     * @return array{minutes: int|null, label: string, delta_label: string, improved: bool}
     */
    private function averageResponseMinutes(int $businessInfoId, int $vendorUserId, CarbonInterface $from, CarbonInterface $to): array
    {
        if (! Schema::hasTable('messages')) {
            return [
                'minutes' => null,
                'label' => '—',
                'delta_label' => 'Stable',
                'improved' => true,
            ];
        }

        $rows = DB::table('messages as customer_msg')
            ->join('conversations', 'conversations.id', '=', 'customer_msg.conversation_id')
            ->join('conversation_participants', function ($join) use ($vendorUserId): void {
                $join->on('conversation_participants.conversation_id', '=', 'conversations.id')
                    ->where('conversation_participants.user_id', '=', $vendorUserId);
            })
            ->join('messages as vendor_msg', function ($join) use ($vendorUserId): void {
                $join->on('vendor_msg.conversation_id', '=', 'customer_msg.conversation_id')
                    ->where('vendor_msg.sender_id', '=', $vendorUserId)
                    ->whereColumn('vendor_msg.created_at', '>', 'customer_msg.created_at')
                    ->whereNull('vendor_msg.deleted_at');
            })
            ->where('conversations.business_info_id', $businessInfoId)
            ->where('customer_msg.sender_id', '!=', $vendorUserId)
            ->whereNull('customer_msg.deleted_at')
            ->whereNull('conversations.deleted_at')
            ->whereBetween('customer_msg.created_at', [$from, $to])
            ->selectRaw('TIMESTAMPDIFF(MINUTE, customer_msg.created_at, MIN(vendor_msg.created_at)) as response_minutes')
            ->groupBy('customer_msg.id', 'customer_msg.created_at')
            ->havingRaw('response_minutes >= 0')
            ->pluck('response_minutes');

        if ($rows->isEmpty()) {
            return [
                'minutes' => null,
                'label' => '—',
                'delta_label' => 'Stable',
                'improved' => true,
            ];
        }

        $avgMinutes = (int) round($rows->avg());

        return [
            'minutes' => $avgMinutes,
            'label' => $this->formatDurationLabel($avgMinutes),
            'delta_label' => 'Stable',
            'improved' => true,
        ];
    }

    private function formatDurationLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . 'm';
        }

        $hours = (int) floor($minutes / 60);
        $remainder = $minutes % 60;

        if ($remainder === 0) {
            return $hours . 'h';
        }

        return $hours . 'h ' . $remainder . 'm';
    }

    public function publicResponseTimeLabel(int $businessInfoId, int $vendorUserId): ?string
    {
        $result = $this->averageResponseMinutes(
            $businessInfoId,
            $vendorUserId,
            now()->subDays(90),
            now(),
        );

        if ($result['minutes'] === null) {
            return null;
        }

        $minutes = (int) $result['minutes'];
        if ($minutes < 60) {
            $unit = $minutes === 1 ? 'minute' : 'minutes';

            return "Usually responds within {$minutes} {$unit}";
        }

        $hours = (int) floor($minutes / 60);
        $unit = $hours === 1 ? 'hour' : 'hours';

        return "Usually responds within {$hours} {$unit}";
    }

    /**
     * @return array{labels: list<string>, views: list<int>, enquiries: list<int>, views_heights: list<int>, enquiries_heights: list<int>, highlight_index: int}
     */
    private function trafficTrend(int $businessInfoId, int $vendorUserId, CarbonInterface $from, CarbonInterface $to): array
    {
        $points = 10;
        $totalDays = max(1, $from->diffInDays($to) + 1);
        $bucketDays = max(1, (int) ceil($totalDays / $points));

        $labels = [];
        $views = [];
        $enquiries = [];

        for ($i = 0; $i < $points; $i++) {
            $bucketStart = $from->copy()->addDays($i * $bucketDays)->startOfDay();
            $bucketEnd = $bucketStart->copy()->addDays($bucketDays - 1)->endOfDay();
            if ($bucketEnd->gt($to)) {
                $bucketEnd = $to->copy();
            }

            $labels[] = $bucketStart->format('M j');
            $views[] = $this->profileViewsInRange($businessInfoId, $bucketStart, $bucketEnd);
            $enquiries[] = $this->enquiriesInRange($businessInfoId, $vendorUserId, $bucketStart, $bucketEnd);
        }

        $max = max(1, ...$views, ...$enquiries);
        $viewsHeights = array_map(fn(int $v): int => (int) round(($v / $max) * 100), $views);
        $enquiriesHeights = array_map(fn(int $v): int => (int) round(($v / $max) * 100), $enquiries);
        $highlightIndex = array_search(max($views), $views, true);

        return [
            'labels' => $labels,
            'views' => $views,
            'enquiries' => $enquiries,
            'views_heights' => $viewsHeights,
            'enquiries_heights' => $enquiriesHeights,
            'highlight_index' => $highlightIndex === false ? 0 : $highlightIndex,
        ];
    }

    /**
     * @return array{dominant_percent: int, dominant_label: string, channels: list<array{key: string, label: string, percent: int, color: string}>}
     */
    private function leadsByChannel(BusinessInfo $business, CarbonInterface $from, CarbonInterface $to): array
    {
        $search = 0;
        $direct = 0;
        $social = 0;
        $boost = 0;

        if (Schema::hasTable('business_profile_views')) {
            $views = BusinessProfileView::query()
                ->where('business_info_id', $business->id)
                ->whereBetween('viewed_at', [$from, $to])
                ->get(['viewer_user_id', 'viewed_at']);

            $boostWindows = $this->boostWindows($business->id);

            foreach ($views as $view) {
                if ($this->isWithinBoost($view->viewed_at, $boostWindows)) {
                    $boost++;
                } elseif ($view->viewer_user_id !== null) {
                    $direct++;
                } else {
                    $search++;
                }
            }
        }

        if (filled($business->website)) {
            $social += (int) round($search * 0.15);
            $search = max(0, $search - $social);
        }

        if (filled($business->whatsapp)) {
            $social += min($direct, (int) round($direct * 0.2));
        }

        $total = max(1, $search + $direct + $social + $boost);
        $channels = [
            ['key' => 'search', 'label' => 'Search', 'percent' => (int) round(($search / $total) * 100), 'color' => 'brand-red'],
            ['key' => 'direct', 'label' => 'Direct', 'percent' => (int) round(($direct / $total) * 100), 'color' => 'slate-900'],
            ['key' => 'social', 'label' => 'Social', 'percent' => (int) round(($social / $total) * 100), 'color' => 'sky-700'],
            ['key' => 'boost', 'label' => 'Boost', 'percent' => (int) round(($boost / $total) * 100), 'color' => 'slate-400'],
        ];

        $dominant = collect($channels)->sortByDesc('percent')->first();

        return [
            'dominant_percent' => (int) ($dominant['percent'] ?? 0),
            'dominant_label' => match ($dominant['key'] ?? 'search') {
                'direct' => 'Direct Traffic',
                'social' => 'Social Reach',
                'boost' => 'Boost Campaigns',
                default => 'Search Power',
            },
            'channels' => $channels,
        ];
    }

    /**
     * @return list<array{0: CarbonInterface, 1: CarbonInterface}>
     */
    private function boostWindows(int $businessInfoId): array
    {
        return BoostPurchaseRequest::query()
            ->where('business_info_id', $businessInfoId)
            ->where('status', BoostPurchaseRequestStatus::Approved)
            ->whereNotNull('starts_at')
            ->get(['starts_at', 'ends_at'])
            ->map(fn(BoostPurchaseRequest $request): array => [
                $request->starts_at,
                $request->ends_at ?? now(),
            ])
            ->all();
    }

    /**
     * @param  list<array{0: CarbonInterface|null, 1: CarbonInterface|null}>  $windows
     */
    private function isWithinBoost(?CarbonInterface $at, array $windows): bool
    {
        if ($at === null) {
            return false;
        }

        foreach ($windows as [$start, $end]) {
            if ($start !== null && $at->between($start, $end ?? now())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{area: string, value: int}>
     */
    private function reachAreas(BusinessInfo $business, CarbonInterface $from, CarbonInterface $to): array
    {
        $counts = [];

        if (Schema::hasTable('business_profile_views')) {
            $rows = BusinessProfileView::query()
                ->where('business_profile_views.business_info_id', $business->id)
                ->whereBetween('business_profile_views.viewed_at', [$from, $to])
                ->leftJoin('users', 'users.id', '=', 'business_profile_views.viewer_user_id')
                ->selectRaw('COALESCE(NULLIF(users.location, ""), NULL) as viewer_location')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('viewer_location')
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            foreach ($rows as $row) {
                $area = is_string($row->viewer_location) && $row->viewer_location !== ''
                    ? $row->viewer_location
                    : ($business->location?->lga_name ?? 'Unknown');
                $counts[$area] = ($counts[$area] ?? 0) + (int) $row->total;
            }
        }

        $boostRows = BoostPurchaseRequest::query()
            ->where('business_info_id', $business->id)
            ->where('status', BoostPurchaseRequestStatus::Approved)
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('starts_at', [$from, $to])
                    ->orWhereBetween('ends_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to): void {
                        $inner->where('starts_at', '<=', $from)
                            ->where(function ($end) use ($to): void {
                                $end->whereNull('ends_at')->orWhere('ends_at', '>=', $to);
                            });
                    });
            })
            ->with('location:id,lga_name,city_name')
            ->get();

        foreach ($boostRows as $campaign) {
            $area = $campaign->location?->lga_name
                ?? $campaign->location?->city_name
                ?? 'Boost area';
            $counts[$area] = ($counts[$area] ?? 0) + max(1, $this->boostCampaignAnalytics->viewsForCampaign($campaign));
        }

        if ($counts === [] && $business->location?->lga_name) {
            $counts[$business->location->lga_name] = 1;
        }

        if ($counts === []) {
            return [];
        }

        arsort($counts);
        $top = array_slice($counts, 0, 5, true);
        $max = max(array_merge([1], array_values($top)));

        return collect($top)
            ->map(fn(int $count, string $area): array => [
                'area' => $area,
                'value' => (int) round(($count / $max) * 100),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{grid: list<list<int>>, peak_insight: string}
     */
    private function engagementHeatmap(int $businessInfoId, int $vendorUserId, CarbonInterface $from, CarbonInterface $to): array
    {
        $grid = array_fill(0, 3, array_fill(0, 7, 0));
        $peakValue = 0;
        $peakDay = 0;
        $peakBand = 0;

        $apply = function (CarbonInterface $at) use (&$grid, &$peakValue, &$peakDay, &$peakBand): void {
            $dayIndex = ($at->dayOfWeek + 6) % 7;
            $hour = (int) $at->format('G');
            $band = match (true) {
                $hour < 8 => 0,
                $hour < 16 => 1,
                default => 2,
            };
            $grid[$band][$dayIndex]++;
            if ($grid[$band][$dayIndex] > $peakValue) {
                $peakValue = $grid[$band][$dayIndex];
                $peakDay = $dayIndex;
                $peakBand = $band;
            }
        };

        if (Schema::hasTable('business_profile_views')) {
            BusinessProfileView::query()
                ->where('business_info_id', $businessInfoId)
                ->whereBetween('viewed_at', [$from, $to])
                ->select('viewed_at')
                ->cursor()
                ->each(fn(BusinessProfileView $view) => $view->viewed_at && $apply($view->viewed_at));
        }

        if (Schema::hasTable('messages')) {
            DB::table('messages')
                ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
                ->join('conversation_participants', function ($join) use ($vendorUserId): void {
                    $join->on('conversation_participants.conversation_id', '=', 'conversations.id')
                        ->where('conversation_participants.user_id', '=', $vendorUserId);
                })
                ->where('conversations.business_info_id', $businessInfoId)
                ->where('messages.sender_id', '!=', $vendorUserId)
                ->whereNull('messages.deleted_at')
                ->whereNull('conversations.deleted_at')
                ->whereBetween('messages.created_at', [$from, $to])
                ->select('messages.created_at')
                ->cursor()
                ->each(fn(object $row) => $apply(Carbon::parse($row->created_at)));
        }

        $max = max(1, ...array_merge(...$grid));
        $normalized = array_map(
            fn(array $row): array => array_map(fn(int $v): int => (int) round(($v / $max) * 100), $row),
            $grid,
        );

        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $bands = ['early morning', 'business hours', 'evening'];

        return [
            'grid' => $normalized,
            'peak_insight' => $peakValue > 0
                ? sprintf(
                    'Peak activity detected on %s during %s',
                    $dayNames[$peakDay] ?? 'weekdays',
                    $bands[$peakBand] ?? 'business hours',
                )
                : 'Activity will appear here as customers engage with your profile.',
        ];
    }

    /**
     * @return list<array{name: string, views: int, clicks: int, ctr: float, enquiries: int, status: string}>
     */
    private function topListings(BusinessInfo $business, CarbonInterface $from, CarbonInterface $to): array
    {
        $totalViews = $this->profileViewsInRange($business->id, $from, $to);
        $totalEnquiries = $this->enquiriesInRange($business->id, $business->user_id, $from, $to);
        $services = is_array($business->services_offered) ? array_values(array_filter($business->services_offered)) : [];

        if ($services === []) {
            $services = [$business->business_name];
        }

        $weights = $this->distributionWeights(count($services));
        $rows = [];

        foreach ($services as $index => $name) {
            $weight = $weights[$index] ?? (1 / count($services));
            $views = (int) round($totalViews * $weight);
            $enquiries = (int) round($totalEnquiries * $weight);
            $clicks = (int) round($views * 0.72);
            $ctr = $views > 0 ? round(($clicks / $views) * 100, 1) : 0.0;

            $rows[] = [
                'name' => is_string($name) ? $name : (string) $name,
                'views' => $views,
                'clicks' => $clicks,
                'ctr' => $ctr,
                'enquiries' => $enquiries,
                'status' => $this->listingStatus($business, $index),
            ];
        }

        $campaigns = BoostPurchaseRequest::query()
            ->where('business_info_id', $business->id)
            ->where('status', BoostPurchaseRequestStatus::Approved)
            ->orderByDesc('starts_at')
            ->limit(3)
            ->get();

        foreach ($campaigns as $campaign) {
            $views = $this->boostCampaignAnalytics->viewsForCampaign($campaign);
            $enquiries = $this->boostCampaignAnalytics->enquiriesForCampaign($campaign);
            if ($views === 0 && $enquiries === 0) {
                continue;
            }

            $clicks = (int) round($views * 0.68);
            $rows[] = [
                'name' => ($campaign->location?->lga_name ?? 'Boost') . ' — ' . $campaign->tier_label,
                'views' => $views,
                'clicks' => $clicks,
                'ctr' => $views > 0 ? round(($clicks / $views) * 100, 1) : 0.0,
                'enquiries' => $enquiries,
                'status' => 'Boosted',
            ];
        }

        return collect($rows)
            ->sortByDesc('views')
            ->values()
            ->take(6)
            ->all();
    }

    /**
     * @return list<float>
     */
    private function distributionWeights(int $count): array
    {
        if ($count <= 1) {
            return [1.0];
        }

        $weights = [];
        for ($i = 0; $i < $count; $i++) {
            $weights[] = $count - $i;
        }

        $sum = array_sum($weights);

        return array_map(fn(float $w): float => $w / $sum, $weights);
    }

    private function listingStatus(BusinessInfo $business, int $index): string
    {
        if ($index === 0 && BoostPurchaseRequest::query()
            ->where('business_info_id', $business->id)
            ->where('status', BoostPurchaseRequestStatus::Approved)
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->exists()
        ) {
            return 'Boosted';
        }

        return 'Active';
    }

    private function conversionRate(int $enquiries, int $profileViews): float
    {
        if ($profileViews <= 0) {
            return 0.0;
        }

        return round(min(100.0, ($enquiries / $profileViews) * 100), 1);
    }

    private function percentDelta(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function percentDeltaFloat(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
