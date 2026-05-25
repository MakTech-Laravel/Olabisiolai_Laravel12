<?php

namespace App\Services;

use App\Enums\BoostPurchaseRequestStatus;
use App\Models\BoostPurchaseRequest;
use App\Models\BusinessInfo;
use App\Models\BusinessProfileView;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BoostCampaignAnalyticsService
{
    public function recordProfileView(BusinessInfo $business, ?User $viewer = null, ?string $ipAddress = null): void
    {
        BusinessProfileView::query()->create([
            'business_info_id' => $business->id,
            'viewer_user_id' => $viewer?->id,
            'viewer_ip_hash' => $ipAddress !== null && $ipAddress !== ''
                ? hash('sha256', $ipAddress)
                : null,
            'viewed_at' => now(),
        ]);
    }

    public function viewsForCampaign(BoostPurchaseRequest $request): int
    {
        [$startsAt, $endsAt] = $this->campaignWindow($request);

        if ($startsAt === null || $endsAt === null) {
            return 0;
        }

        return BusinessProfileView::query()
            ->where('business_info_id', $request->business_info_id)
            ->whereBetween('viewed_at', [$startsAt, $endsAt])
            ->count();
    }

    public function enquiriesForCampaign(BoostPurchaseRequest $request): int
    {
        [$startsAt, $endsAt] = $this->campaignWindow($request);

        if ($startsAt === null || $endsAt === null) {
            return 0;
        }

        $vendorUserId = BusinessInfo::query()
            ->whereKey($request->business_info_id)
            ->value('user_id');

        if ($vendorUserId === null) {
            return 0;
        }

        return (int) DB::table('messages')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('conversation_participants', function ($join) use ($vendorUserId): void {
                $join->on('conversation_participants.conversation_id', '=', 'conversations.id')
                    ->where('conversation_participants.user_id', '=', $vendorUserId);
            })
            ->where('messages.sender_id', '!=', $vendorUserId)
            ->whereNull('messages.deleted_at')
            ->whereNull('conversations.deleted_at')
            ->whereBetween('messages.created_at', [$startsAt, $endsAt])
            ->distinct()
            ->count('conversations.id');
    }

    /**
     * @param  iterable<int, BoostPurchaseRequest>  $campaigns
     */
    public function attachCountsToCampaigns(iterable $campaigns): void
    {
        foreach ($campaigns as $campaign) {
            if ((bool) ($campaign->metadata['is_extension_record'] ?? false)) {
                $campaign->setAttribute('computed_views_count', 0);
                $campaign->setAttribute('computed_enquiries_count', 0);

                continue;
            }

            $campaign->setAttribute('computed_views_count', $this->viewsForCampaign($campaign));
            $campaign->setAttribute('computed_enquiries_count', $this->enquiriesForCampaign($campaign));
        }
    }

    /**
     * @return array{0: CarbonInterface|null, 1: CarbonInterface|null}
     */
    private function campaignWindow(BoostPurchaseRequest $request): array
    {
        if ($request->status !== BoostPurchaseRequestStatus::Approved) {
            return [null, null];
        }

        if (! $request->starts_at instanceof CarbonInterface) {
            return [null, null];
        }

        $endsAt = $request->ends_at instanceof CarbonInterface
            ? ($request->ends_at->isFuture() ? now() : $request->ends_at)
            : now();

        return [$request->starts_at, $endsAt];
    }
}
