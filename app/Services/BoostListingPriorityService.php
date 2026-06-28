<?php

namespace App\Services;

use App\Enums\BoostPurchaseRequestStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Orders public marketplace listings using the Gidira search hierarchy:
 * 1. Premium + Verified + Boosted
 * 2. Premium + Verified
 * 3. Premium
 * 4. Verified
 * 5. Free
 *
 * Within the same tier: proximity (nearest first), then highest active boost daily budget.
 */
class BoostListingPriorityService
{
    public const TIER_RANK_TOP_1 = 1;

    public const TIER_RANK_TOP_5 = 2;

    public const TIER_RANK_TOP_10 = 3;

    public const TIER_RANK_DYNAMIC = 10;

    public const TIER_RANK_NONE = 999;

    private const NEARBY_PRIORITY_RADIUS_KM = 0.804672;

    /**
     * @param  array<string, mixed>  $context
     *   Supports `location_id` (int), `location_ids` (list<int>), and optional
     *   `proximity` => ['lat' => float, 'lng' => float].
     */
    public function applyToQuery(Builder $query, array $context = []): void
    {
        [$locationId, $locationIds] = $this->normalizeLocationContext($context);

        $prioritySql = $this->activeTierPrioritySubquerySql($locationId, $locationIds);
        $dailyBudgetSql = $this->activeDailyBudgetSubquerySql($locationId, $locationIds);

        $query->orderByRaw('('.$this->searchHierarchyRankSql($locationId, $locationIds).') ASC');

        $proximity = $context['proximity'] ?? null;
        if (is_array($proximity) && isset($proximity['lat'], $proximity['lng'])) {
            $this->applyProximityOrdering(
                $query,
                (float) $proximity['lat'],
                (float) $proximity['lng'],
            );
        }

        $query->orderByRaw("({$dailyBudgetSql}) DESC");
        $query->orderByRaw("({$prioritySql}) ASC");
        $query->orderByDesc('sort_order');
        $query->orderByDesc('average_rating');
        $query->orderByDesc('created_at');
    }

    public function scopeActiveCampaign(Builder $query, ?int $locationId = null): Builder
    {
        $query
            ->where('status', BoostPurchaseRequestStatus::Approved)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now());

        if ($locationId !== null && $locationId > 0) {
            $query->where('location_id', $locationId);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: ?int, 1: list<int>}
     */
    private function normalizeLocationContext(array $context): array
    {
        $locationId = isset($context['location_id']) ? (int) $context['location_id'] : null;
        if ($locationId <= 0) {
            $locationId = null;
        }

        $locationIds = [];
        if (isset($context['location_ids']) && is_array($context['location_ids'])) {
            $locationIds = array_values(array_unique(array_filter(
                array_map(static fn ($id): int => (int) $id, $context['location_ids']),
                static fn (int $id): bool => $id > 0,
            )));
        }

        if ($locationId !== null) {
            return [$locationId, []];
        }

        return [null, $locationIds];
    }

    /**
     * @param  list<int>  $filterLocationIds
     */
    private function activeCampaignLocationClause(?int $filterLocationId, array $filterLocationIds = []): string
    {
        if ($filterLocationId !== null) {
            return 'AND bpr.location_id = '.(int) $filterLocationId;
        }

        if ($filterLocationIds !== []) {
            return 'AND bpr.location_id IN ('.implode(',', $filterLocationIds).')'
                .' AND bpr.location_id = business_info.location_id';
        }

        return 'AND bpr.location_id = business_info.location_id';
    }

    /**
     * @param  list<int>  $filterLocationIds
     */
    private function activeTierPrioritySubquerySql(?int $filterLocationId, array $filterLocationIds = []): string
    {
        $approved = BoostPurchaseRequestStatus::Approved->value;
        $locationClause = $this->activeCampaignLocationClause($filterLocationId, $filterLocationIds);

        $tierCase = 'CASE bpr.tier_key
                WHEN \'top_1\' THEN '.self::TIER_RANK_TOP_1.'
                WHEN \'top_5\' THEN '.self::TIER_RANK_TOP_5.'
                WHEN \'top_10\' THEN '.self::TIER_RANK_TOP_10.'
                WHEN \'dynamic\' THEN '.self::TIER_RANK_DYNAMIC.'
                ELSE 99
            END';

        return "COALESCE((
            SELECT MIN({$tierCase})
            FROM boost_purchase_requests bpr
            WHERE bpr.business_info_id = business_info.id
              AND bpr.status = '{$approved}'
              AND bpr.starts_at IS NOT NULL
              AND bpr.starts_at <= NOW()
              AND bpr.ends_at IS NOT NULL
              AND bpr.ends_at > NOW()
              {$locationClause}
        ), ".self::TIER_RANK_NONE.')';
    }

    /**
     * @param  list<int>  $filterLocationIds
     */
    private function activeDailyBudgetSubquerySql(?int $filterLocationId, array $filterLocationIds = []): string
    {
        $approved = BoostPurchaseRequestStatus::Approved->value;
        $locationClause = $this->activeCampaignLocationClause($filterLocationId, $filterLocationIds);

        $budgetExpression = 'COALESCE(
            CAST(JSON_UNQUOTE(JSON_EXTRACT(bpr.metadata, \'$.daily_budget\')) AS DECIMAL(12,2)),
            CASE WHEN bpr.duration_days > 0 THEN bpr.amount / bpr.duration_days ELSE bpr.amount END
        )';

        return "COALESCE((
            SELECT MAX({$budgetExpression})
            FROM boost_purchase_requests bpr
            WHERE bpr.business_info_id = business_info.id
              AND bpr.status = '{$approved}'
              AND bpr.starts_at IS NOT NULL
              AND bpr.starts_at <= NOW()
              AND bpr.ends_at IS NOT NULL
              AND bpr.ends_at > NOW()
              {$locationClause}
        ), 0)";
    }

    /**
     * @param  list<int>  $filterLocationIds
     */
    private function searchHierarchyRankSql(?int $filterLocationId, array $filterLocationIds = []): string
    {
        $approved = VerificationStatus::Approved->value;
        $premiumPlan = SubscriptionPlan::Premium->value;
        $activeStatus = SubscriptionStatus::Active->value;
        $activeBoostSql = $this->activeTierPrioritySubquerySql($filterLocationId, $filterLocationIds);

        $hasPremium = "EXISTS (
            SELECT 1 FROM business_subscriptions bs
            WHERE bs.business_info_id = business_info.id
              AND bs.plan = '{$premiumPlan}'
              AND bs.status = '{$activeStatus}'
              AND (bs.expires_at IS NULL OR bs.expires_at > NOW())
        )";

        $isVerified = "business_info.verification_status = '{$approved}'";
        $hasActiveBoost = "({$activeBoostSql}) < ".self::TIER_RANK_NONE;

        return "CASE
            WHEN {$hasPremium} AND {$isVerified} AND {$hasActiveBoost} THEN 1
            WHEN {$hasPremium} AND {$isVerified} THEN 2
            WHEN {$hasPremium} THEN 3
            WHEN {$isVerified} THEN 4
            ELSE 5
        END";
    }

    private function applyProximityOrdering(Builder $query, float $lat, float $lng): void
    {
        $nearKm = self::NEARBY_PRIORITY_RADIUS_KM;

        $businessLat = 'COALESCE(business_info.latitude, (SELECT l.latitude FROM locations l WHERE l.id = business_info.location_id LIMIT 1))';
        $businessLng = 'COALESCE(business_info.longitude, (SELECT l.longitude FROM locations l WHERE l.id = business_info.location_id LIMIT 1))';

        $distanceSql = "(6371 * acos(LEAST(1, GREATEST(-1, cos(radians(?)) * cos(radians({$businessLat})) * cos(radians({$businessLng}) - radians(?)) + sin(radians(?)) * sin(radians({$businessLat}))))))";

        $query->orderByRaw(
            "CASE WHEN {$businessLat} IS NOT NULL AND {$businessLng} IS NOT NULL AND ({$distanceSql}) <= ? THEN 0 ELSE 1 END ASC",
            [$lat, $lng, $lat, $nearKm],
        );
        $query->orderByRaw("({$distanceSql}) ASC", [$lat, $lng, $lat]);
    }
}
