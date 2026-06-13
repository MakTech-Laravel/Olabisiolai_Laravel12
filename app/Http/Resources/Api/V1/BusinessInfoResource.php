<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\BoostPurchaseRequestStatus;
use App\Models\BoostPurchaseRequest;
use App\Services\BoostListingPriorityService;
use App\Services\BusinessHoursService;
use App\Services\SubscriptionService;
use App\Services\VerificationService;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessInfoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $coverPaths = is_array($this->cover_photo_paths) ? $this->cover_photo_paths : [];
        $subscriptionService = app(SubscriptionService::class);
        $verificationService = app(VerificationService::class);
        $businessHoursService = app(BusinessHoursService::class);
        $subscription = $this->relationLoaded('subscription')
            ? $this->subscription
            : $this->resource->subscription;

        return [
            'id' => $this->id,
            'sort_order' => $this->sort_order,
            'business_name' => $this->business_name,
            'street_address' => $this->street_address,
            'full_address' => $this->street_address,
            'vendor' => $this->when(
                $this->relationLoaded('user') && $this->user !== null,
                fn() => [
                    'id' => $this->user->id,
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                    'phone_formatted' => PhoneNormalizer::formatInternational($this->user->phone) ?? $this->user->phone,
                    'role' => $this->user->role,
                ]
            ),
            'category_id' => $this->category_id,
            'category' => $this->when(
                $this->relationLoaded('category') && $this->category !== null,
                fn() => new CategoryResource($this->category)
            ),
            'subcategory' => $this->subcategory,
            'location_id' => $this->location_id,
            'location' => $this->when(
                $this->relationLoaded('location') && $this->location !== null,
                fn() => [
                    'id' => $this->location->id,
                    'name' => $this->location->lga_name,
                    'state' => $this->location->state_name,
                    'city' => $this->location->city_name,
                    'full_name' => $this->location->full_name,
                    'formatted_address' => $this->location->displayFormattedAddress(),
                    'latitude' => $this->location->resolvedLatitude(),
                    'longitude' => $this->location->resolvedLongitude(),
                ]
            ),
            'business_description' => $this->business_description,
            'services_offered' => $this->services_offered ?? [],
            'phone' => $this->phone,
            'phone_formatted' => PhoneNormalizer::formatInternational($this->phone) ?? $this->phone,
            'whatsapp' => $this->whatsapp,
            'whatsapp_formatted' => PhoneNormalizer::formatInternational($this->whatsapp) ?? $this->whatsapp,
            'website' => $this->website,
            'social_accounts' => is_array($this->social_accounts) ? $this->social_accounts : [],
            'logo_url' => public_media_url($this->logo_path),
            'cover_photo_urls' => collect($coverPaths)
                ->filter(fn($path) => is_string($path) && $path !== '')
                ->map(fn(string $path) => public_media_url($path, null))
                ->filter()
                ->values()
                ->all(),
            'cover_photo_paths' => collect($coverPaths)
                ->filter(fn($path) => is_string($path) && $path !== '')
                ->values()
                ->all(),
            'verification_status' => $this->verification_status->value,
            'is_flagged' => (bool) $this->is_flagged,
            'is_approved' => $this->verification_status->value === 'approved',
            'shows_verified_badge' => $verificationService->showsVerifiedBadge($this->resource),
            'is_verified' => $verificationService->showsVerifiedBadge($this->resource),
            'member_since' => humanDateTime($this->created_at, 'F Y'),
            'verified_since' => $verificationService->showsVerifiedBadge($this->resource)
                ? humanDateTime($this->verified_at ?? $this->updated_at, 'F Y')
                : null,
            'is_premium' => $subscriptionService->hasActivePremium($this->resource),
            'subscription_plan' => $subscription?->plan->value,
            'subscription_status' => $subscription?->status->value,
            'subscription_expires_at' => $subscription?->expires_at ? humanDateTime($subscription->expires_at) : null,
            'subscription_expires_at_iso' => $subscription?->expires_at?->toIso8601String(),
            'requires_subscription_payment' => $subscriptionService->requiresPayment($this->resource),
            'can_pay_premium' => $subscriptionService->canPayForPremium($this->resource),
            'is_premium_active' => $subscriptionService->hasActivePremium($this->resource),
            'can_access_features' => $subscriptionService->canAccessVendorFeatures($this->resource),
            'business_status' => $this->business_status->value,
            'average_rating' => round((float) ($this->average_rating ?? 0), 1),
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'is_favorite' => (bool) ($this->is_favorite ?? false),
            'boost_status' => $this->resolvePublicBoostStatus(),
            'active_boost_tier' => $this->resolveActiveBoostTierKey(),
            'business_hours' => $businessHoursService->serializeForBusiness($this->resource),
            'business_hours_display' => $businessHoursService->buildDisplayGroups($this->resource),
            'created_at' => humanDateTime($this->created_at),
            'updated_at' => humanDateTime($this->updated_at),
        ];
    }

    private function resolveActiveBoostTierKey(): ?string
    {
        $preset = $this->resource->getAttribute('active_boost_tier_key');
        if (is_string($preset) && $preset !== '') {
            return $preset;
        }

        $locationId = $this->relationLoaded('location') && $this->location !== null
            ? (int) $this->location->id
            : (int) $this->location_id;

        $query = BoostPurchaseRequest::query()
            ->where('business_info_id', $this->id)
            ->where('status', BoostPurchaseRequestStatus::Approved)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now());

        if ($locationId > 0) {
            $query->where('location_id', $locationId);
        }

        return $query
            ->orderByRaw(
                'CASE tier_key
                    WHEN \'top_1\' THEN ' . BoostListingPriorityService::TIER_RANK_TOP_1 . '
                    WHEN \'top_5\' THEN ' . BoostListingPriorityService::TIER_RANK_TOP_5 . '
                    WHEN \'top_10\' THEN ' . BoostListingPriorityService::TIER_RANK_TOP_10 . '
                    ELSE 99
                END ASC',
            )
            ->value('tier_key');
    }

    private function resolvePublicBoostStatus(): string
    {
        if ($this->resolveActiveBoostTierKey() !== null) {
            return 'active';
        }

        return ($this->relationLoaded('boost') && $this->boost?->is_active) ? 'active' : 'none';
    }
}
