<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\BoostPurchaseRequestStatus;
use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoostPurchaseRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $location = $this->location;
        $business = $this->businessInfo;
        $displayStatus = $this->resolveDisplayStatus();
        $durationLeft = $this->formatDurationLeft();
        $isPendingAssignment = in_array($this->status, [
            BoostPurchaseRequestStatus::PendingAdmin,
            BoostPurchaseRequestStatus::PendingPayment,
        ], true);

        return [
            'id' => $this->id,
            'tier_key' => $this->tier_key,
            'tier_label' => $this->tier_label,
            'tier_badge' => $this->resolveTierBadge(),
            'duration_days' => $this->duration_days,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'display_status' => $displayStatus,
            'display_status_label' => $this->resolveDisplayStatusLabel($displayStatus),
            'duration_left_label' => $durationLeft,
            'views_count' => $this->resolveCampaignViews(),
            'enquiries_count' => $this->resolveCampaignEnquiries(),
            'payment_id' => $this->payment_id,
            'can_continue_payment' => $this->status === BoostPurchaseRequestStatus::PendingPayment
                && $this->relationLoaded('payment')
                && $this->payment?->status === PaymentStatus::Pending,
            'can_extend' => $displayStatus === 'active' && ! (bool) ($this->metadata['is_extension_record'] ?? false),
            'can_boost_again' => $displayStatus === 'expired',
            'renew_type' => $this->metadata['renew_type'] ?? null,
            'source_campaign_id' => isset($this->metadata['source_campaign_id'])
                ? (int) $this->metadata['source_campaign_id']
                : null,
            'is_extension_record' => (bool) ($this->metadata['is_extension_record'] ?? false),
            'extension_parent_id' => isset($this->metadata['extension_parent_id'])
                ? (int) $this->metadata['extension_parent_id']
                : null,
            'extensions' => $this->metadata['extensions'] ?? [],
            'starts_on_assign' => $isPendingAssignment,
            'projected_ends_at' => $isPendingAssignment
                ? now()->addDays((int) $this->duration_days)->toIso8601String()
                : null,
            'is_flagged' => (bool) $this->is_flagged,
            'admin_note' => $this->admin_note,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'waiting_rank' => $this->when(isset($this->waiting_rank), $this->waiting_rank),
            'business' => $business ? [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'vendor_name' => $business->relationLoaded('user') ? $business->user?->name : null,
                'vendor_email' => $business->relationLoaded('user') ? $business->user?->email : null,
                'category_name' => $business->relationLoaded('category') ? $business->category?->name : null,
            ] : null,
            'location' => $location ? [
                'id' => $location->id,
                'label' => trim(implode(' / ', array_filter([
                    $location->state_name,
                    $location->city_name,
                    $location->lga_name,
                ]))),
                'state' => $location->state_name,
                'city' => $location->city_name,
                'lga' => $location->lga_name,
            ] : null,
            'metadata' => $this->metadata,
        ];
    }

    private function resolveDisplayStatus(): string
    {
        if ($this->status === BoostPurchaseRequestStatus::Approved) {
            if ((bool) ($this->metadata['is_extension_record'] ?? false)) {
                return 'extension_merged';
            }

            if ($this->ends_at instanceof CarbonInterface && $this->ends_at->isPast()) {
                return 'expired';
            }

            return 'active';
        }

        return $this->status->value;
    }

    private function resolveDisplayStatusLabel(string $displayStatus): string
    {
        return match ($displayStatus) {
            'active' => 'Active',
            'expired' => 'Expired',
            'extension_merged' => 'Merged',
            'pending_admin' => 'Pending approval',
            'pending_payment' => 'Awaiting payment',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $displayStatus)),
        };
    }

    private function resolveTierBadge(): string
    {
        return match ($this->tier_key) {
            'top_1' => 'GOLD',
            'top_5' => 'SILVER',
            'top_10' => 'BRONZE',
            'top_3' => 'SILVER',
            default => strtoupper($this->tier_key),
        };
    }

    private function formatDurationLeft(): ?string
    {
        if ($this->status !== BoostPurchaseRequestStatus::Approved) {
            return null;
        }

        if (! $this->ends_at instanceof CarbonInterface) {
            return null;
        }

        if ($this->ends_at->isPast()) {
            return 'Ended';
        }

        $diff = now()->diff($this->ends_at);

        if ($diff->days > 0) {
            return sprintf('%dd %dh', $diff->days, $diff->h);
        }

        if ($diff->h > 0) {
            return sprintf('%dh %dm', $diff->h, $diff->i);
        }

        return sprintf('%dm', max(1, $diff->i));
    }

    private function resolveCampaignViews(): int
    {
        if ($this->getAttribute('computed_views_count') !== null) {
            return max(0, (int) $this->getAttribute('computed_views_count'));
        }

        $meta = $this->metadata ?? [];
        if (isset($meta['views_count'])) {
            return max(0, (int) $meta['views_count']);
        }

        return 0;
    }

    private function resolveCampaignEnquiries(): int
    {
        if ($this->getAttribute('computed_enquiries_count') !== null) {
            return max(0, (int) $this->getAttribute('computed_enquiries_count'));
        }

        $meta = $this->metadata ?? [];
        if (isset($meta['enquiries_count'])) {
            return max(0, (int) $meta['enquiries_count']);
        }

        return 0;
    }
}
