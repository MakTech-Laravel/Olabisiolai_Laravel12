<?php

namespace App\Services;

use App\Enums\BoostPurchaseRequestStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Orders public marketplace listings so active LGA boost campaigns surface first
 * (top_1, then top_5, then top_10) before non-boosted businesses.
 */
class BoostListingPriorityService
{
    public const TIER_RANK_TOP_1 = 1;

    public const TIER_RANK_TOP_5 = 2;

    public const TIER_RANK_TOP_10 = 3;

    public const TIER_RANK_NONE = 999;

    /**
     * @param  array<string, mixed>  $context  Supports `location_id` (int) for LGA-scoped boosts.
     */
    public function applyToQuery(Builder $query, array $context = []): void
    {
        [$locationId, $locationIds] = $this->normalizeLocationContext($context);

        $prioritySql = $this->activeTierPrioritySubquerySql($locationId, $locationIds);

        $query->orderByRaw('(' . $this->trustTierRankSql() . ') ASC');
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
                array_map(static fn($id): int => (int) $id, $context['location_ids']),
                static fn(int $id): bool => $id > 0,
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
    private function activeTierPrioritySubquerySql(?int $filterLocationId, array $filterLocationIds = []): string
    {
        $approved = BoostPurchaseRequestStatus::Approved->value;

        $tierCase = 'CASE bpr.tier_key
                WHEN \'top_1\' THEN ' . self::TIER_RANK_TOP_1 . '
                WHEN \'top_5\' THEN ' . self::TIER_RANK_TOP_5 . '
                WHEN \'top_10\' THEN ' . self::TIER_RANK_TOP_10 . '
                ELSE 99
            END';

        if ($filterLocationId !== null) {
            $locationClause = 'AND bpr.location_id = ' . (int) $filterLocationId;
        } elseif ($filterLocationIds !== []) {
            $locationClause = 'AND bpr.location_id IN (' . implode(',', $filterLocationIds) . ')'
                . ' AND bpr.location_id = business_info.location_id';
        } else {
            $locationClause = 'AND bpr.location_id = business_info.location_id';
        }

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
        ), " . self::TIER_RANK_NONE . ')';
    }

    /**
     * Verified + premium listings rank above verified free, then unverified.
     * Active boost tiers still apply within each trust band via {@see applyToQuery}.
     */
    private function trustTierRankSql(): string
    {
        $approved = VerificationStatus::Approved->value;
        $premiumPlan = SubscriptionPlan::Premium->value;
        $activeStatus = SubscriptionStatus::Active->value;

        return "CASE
            WHEN business_info.verification_status = '{$approved}' AND EXISTS (
                SELECT 1 FROM business_subscriptions bs
                WHERE bs.business_info_id = business_info.id
                  AND bs.plan = '{$premiumPlan}'
                  AND bs.status = '{$activeStatus}'
                  AND (bs.expires_at IS NULL OR bs.expires_at > NOW())
            ) THEN 1
            WHEN business_info.verification_status = '{$approved}' THEN 2
            ELSE 3
        END";
    }
}
