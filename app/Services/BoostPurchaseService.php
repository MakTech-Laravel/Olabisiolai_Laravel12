<?php

namespace App\Services;

use App\Enums\BoostPurchaseRequestStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use Illuminate\Support\Carbon;
use App\Models\Admin;
use App\Models\Boost;
use App\Models\BoostPurchaseRequest;
use App\Models\BusinessInfo;
use App\Models\LgaBoost;
use App\Models\Location;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BoostPurchaseService
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly BoostCampaignAnalyticsService $campaignAnalytics,
        private readonly LocationService $locationService,
    ) {}

    public function dynamicTierKey(): string
    {
        return (string) config('boost.dynamic.tier_key', 'dynamic');
    }

    public function isDynamicTier(?string $tierKey): bool
    {
        if ($tierKey === null || $tierKey === '') {
            return true;
        }

        return $tierKey === $this->dynamicTierKey();
    }

    /**
     * @return list<int>
     */
    public function dynamicDurations(): array
    {
        return array_values(array_map('intval', config('boost.dynamic.durations', [1, 3, 7])));
    }

    public function assertDynamicBoost(int $durationDays, float $dailyBudget): void
    {
        $durations = $this->dynamicDurations();

        if (! in_array($durationDays, $durations, true)) {
            throw new RuntimeException('Select a valid boost duration ('.implode(', ', $durations).' days).');
        }

        $min = (float) config('boost.dynamic.budget_min', 500);
        $max = (float) config('boost.dynamic.budget_max', 5000);

        if ($dailyBudget < $min || $dailyBudget > $max) {
            throw new RuntimeException('Daily boost budget must be between ₦'.number_format($min).' and ₦'.number_format($max).'.');
        }
    }

    public function assertLocationHasBoostEnabled(Location $location): LgaBoost
    {
        $location->loadMissing('lgaBoost');
        $lgaBoost = $location->lgaBoost;

        if ($lgaBoost === null || ! $lgaBoost->enabled) {
            $label = trim((string) ($location->full_name ?? $location->lga_name ?? 'this location'));

            Log::warning('boost.location_not_enabled', [
                'location_id' => $location->id,
                'lga_name' => $location->lga_name,
                'has_lga_boost_record' => $lgaBoost !== null,
                'enabled' => $lgaBoost?->enabled,
            ]);

            throw new RuntimeException("Boost is not enabled for {$label}. Choose another LGA or contact support.");
        }

        return $lgaBoost;
    }

    /**
     * @return array{amount: float, daily_budget: float, tier_label: string}
     */
    public function resolveDynamicBoostPrice(float $dailyBudget, int $durationDays): array
    {
        $this->assertDynamicBoost($durationDays, $dailyBudget);

        return [
            'amount' => round($dailyBudget * $durationDays, 2),
            'daily_budget' => round($dailyBudget, 2),
            'tier_label' => (string) config('boost.dynamic.tier_label', 'Dynamic Boost'),
        ];
    }

    /**
     * @return BoostPurchaseRequest|null
     */
    public function findResumablePendingPaymentRequest(
        BusinessInfo $business,
        ?string $renewType = null,
        ?int $sourceCampaignId = null,
        ?string $tierKey = null,
    ): ?BoostPurchaseRequest {
        return BoostPurchaseRequest::query()
            ->where('business_info_id', $business->id)
            ->where('status', BoostPurchaseRequestStatus::PendingPayment)
            ->when($tierKey !== null, fn($query) => $query->where('tier_key', $tierKey))
            ->when($renewType !== null, fn($query) => $query->where('metadata->renew_type', $renewType))
            ->when($sourceCampaignId !== null, fn($query) => $query->where('metadata->source_campaign_id', $sourceCampaignId))
            ->with('payment')
            ->latest('id')
            ->first();
    }

    /**
     * @return array{payment: Payment, request: BoostPurchaseRequest}
     */
    public function resumeBoostPayment(BusinessInfo $business, int $requestId): array
    {
        $boostRequest = BoostPurchaseRequest::query()
            ->where('id', $requestId)
            ->where('business_info_id', $business->id)
            ->where('status', BoostPurchaseRequestStatus::PendingPayment)
            ->with('payment')
            ->first();

        if ($boostRequest === null) {
            throw new RuntimeException('No unpaid boost checkout was found for this request.');
        }

        $payment = $boostRequest->payment;

        if ($payment === null || $payment->status !== PaymentStatus::Pending) {
            throw new RuntimeException('This payment session is no longer available. Start a new boost checkout.');
        }

        return [
            'payment' => $payment,
            'request' => $boostRequest,
        ];
    }

    public function initBoostPayment(
        User $user,
        BusinessInfo $business,
        string $tierKey,
        int $durationDays,
        ?string $renewType = null,
        ?int $sourceCampaignId = null,
        ?int $targetLocationId = null,
        ?PaymentGateway $gateway = null,
        ?float $budgetAmount = null,
    ): array {
        $tierKey = $this->isDynamicTier($tierKey) ? $this->dynamicTierKey() : $tierKey;
        $renewType = in_array($renewType, ['extend', 'boost_again'], true) ? $renewType : null;
        $location = $this->resolveBoostTargetLocation($business, $targetLocationId, $sourceCampaignId, $renewType);
        $forBusinessId = $renewType !== null ? $business->id : null;

        $existing = $this->findResumablePendingPaymentRequest(
            $business,
            $renewType,
            $sourceCampaignId,
            $tierKey,
        );

        if ($existing !== null) {
            $existing->loadMissing('payment');
            $existingPayment = $existing->payment;

            if ($existingPayment !== null && $existingPayment->status === PaymentStatus::Pending) {
                return [
                    'payment' => $existingPayment,
                    'request' => $existing,
                ];
            }
        }

        if ($this->isDynamicTier($tierKey)) {
            if ($budgetAmount === null) {
                throw new RuntimeException('Boost budget is required.');
            }
            $this->assertLocationHasBoostEnabled($location);
            $this->assertDynamicBoost($durationDays, $budgetAmount);
            $pricing = $this->resolveDynamicBoostPrice($budgetAmount, $durationDays);
        } else {
            $lgaBoost = $this->assertBoostAvailableForLocation($location, $tierKey, $durationDays, $forBusinessId, $renewType);
            $pricing = $this->resolveTierDurationPrice($lgaBoost, $tierKey, $durationDays);
        }

        $paymentMetadata = [
            'boost_tier_key' => $tierKey,
            'boost_tier_label' => $pricing['tier_label'],
            'boost_duration_days' => $durationDays,
            'renew_type' => $renewType,
            'source_campaign_id' => $sourceCampaignId,
            'location_label' => $location->full_name,
            'boost_model' => $this->isDynamicTier($tierKey) ? 'dynamic' : 'slot_tier',
        ];

        if ($this->isDynamicTier($tierKey) && $budgetAmount !== null) {
            $paymentMetadata['boost_daily_budget'] = $pricing['daily_budget'];
            $paymentMetadata['boost_total_amount'] = $pricing['amount'];
        }

        $payment = $this->paymentService->initBoostPayment($user, $business, $pricing['amount'], $paymentMetadata, null, $gateway);

        $request = $this->createRequest(
            $user,
            $business,
            $tierKey,
            $durationDays,
            BoostPurchaseRequestStatus::PendingPayment,
            $payment,
            $renewType,
            $sourceCampaignId,
            (int) $location->id,
            $budgetAmount,
        );

        return [
            'payment' => $payment,
            'request' => $request,
        ];
    }

    public function confirmBoostPayment(Payment $payment, string $gatewayTransactionId, PaymentGateway $gateway): BoostPurchaseRequest
    {
        if ($payment->purpose !== PaymentPurpose::Boost) {
            throw new RuntimeException('Invalid payment for boost checkout.');
        }

        if ($payment->status === PaymentStatus::Pending) {
            $payment = $this->paymentService->confirmPayment($payment, $gatewayTransactionId, $gateway);
        } elseif ($payment->gateway === null) {
            $payment->update(['gateway' => $gateway]);
            $payment = $payment->fresh();
        }

        $request = $this->markPaidAndQueueForAdmin($payment);

        if ($request === null) {
            throw new RuntimeException('Boost request was not found for this payment.');
        }

        return $request->fresh(['location', 'businessInfo']);
    }

    /**
     * @return array{amount: float, tier_label: string}
     */
    public function resolveTierDurationPrice(LgaBoost $lgaBoost, string $tierKey, int $durationDays): array
    {
        $tiers = collect($lgaBoost->tiers ?? []);
        $tier = $tiers->first(fn(array $row): bool => ($row['key'] ?? '') === $tierKey);

        if ($tier === null) {
            throw new RuntimeException('Selected boost plan is not available for this location.');
        }

        $tierLabel = (string) ($tier['label'] ?? $tierKey);
        $tierDurations = collect($tier['durations'] ?? []);
        $durationRow = $tierDurations->first(fn(array $row): bool => (int) ($row['days'] ?? 0) === $durationDays);

        if ($durationRow !== null) {
            if (! ($durationRow['enabled'] ?? true)) {
                throw new RuntimeException('Selected duration is not enabled for this boost plan.');
            }

            $amount = (float) ($durationRow['price_amount'] ?? 0);
            if ($amount > 0) {
                return ['amount' => $amount, 'tier_label' => $tierLabel];
            }
        }

        $globalDurations = collect($lgaBoost->durations ?? []);
        $globalRow = $globalDurations->first(fn(array $row): bool => (int) ($row['days'] ?? 0) === $durationDays);

        if ($globalRow !== null && ($globalRow['enabled'] ?? false)) {
            $amount = (float) ($globalRow['price_amount'] ?? 0);
            if ($amount > 0) {
                return ['amount' => $amount, 'tier_label' => $tierLabel];
            }
        }

        $fallback = (float) ($tier['price_amount'] ?? 0);
        if ($fallback > 0) {
            return ['amount' => $fallback, 'tier_label' => $tierLabel];
        }

        throw new RuntimeException('No price configured for the selected boost plan and duration.');
    }

    public function assertBoostAvailableForLocation(
        Location $location,
        string $tierKey,
        int $durationDays,
        ?int $forBusinessId = null,
        ?string $renewType = null,
    ): LgaBoost {
        if ($this->isDynamicTier($tierKey)) {
            $this->assertDynamicBoost($durationDays, (float) config('boost.dynamic.budget_min', 500));

            throw new RuntimeException('Dynamic boosts do not use slot availability checks.');
        }

        $location->loadMissing('lgaBoost');
        $lgaBoost = $location->lgaBoost;

        if ($lgaBoost === null || ! $lgaBoost->enabled) {
            $label = trim((string) ($location->full_name ?? $location->lga_name ?? 'this location'));
            throw new RuntimeException("Boost is not enabled for {$label}. Choose another LGA or contact support.");
        }

        if (! in_array($durationDays, [7, 14, 30], true)) {
            throw new RuntimeException('Invalid boost duration selected.');
        }

        $this->resolveTierDurationPrice($lgaBoost, $tierKey, $durationDays);

        $tiers = collect($lgaBoost->tiers ?? []);
        $tier = $tiers->first(fn(array $row): bool => ($row['key'] ?? '') === $tierKey);
        $totalSlots = (int) ($tier['total_slots'] ?? 0);
        $tierLabel = (string) ($tier['label'] ?? $tierKey);

        if ($renewType === 'extend' && $forBusinessId !== null) {
            $ownsActiveSlot = BoostPurchaseRequest::query()
                ->where('business_info_id', $forBusinessId)
                ->where('location_id', $location->id)
                ->where('tier_key', $tierKey)
                ->where('status', BoostPurchaseRequestStatus::Approved)
                ->where('ends_at', '>', now())
                ->exists();

            if ($ownsActiveSlot) {
                return $lgaBoost;
            }
        }

        if ($totalSlots > 0 && $this->remainingSlotsForTier($lgaBoost, $tierKey, null, $forBusinessId) <= 0) {
            throw new RuntimeException(
                "No slots available for {$tierLabel} in this LGA. All spots are currently booked. Choose another plan or location.",
            );
        }

        return $lgaBoost;
    }

    public function countOccupiedSlotsForTier(
        int $locationId,
        string $tierKey,
        ?int $excludeRequestId = null,
        ?int $forBusinessId = null,
    ): int {
        return BoostPurchaseRequest::query()
            ->where('location_id', $locationId)
            ->where('tier_key', $tierKey)
            ->when($excludeRequestId !== null, fn($query) => $query->where('id', '!=', $excludeRequestId))
            ->when($forBusinessId !== null, fn($query) => $query->where('business_info_id', '!=', $forBusinessId))
            ->where(function ($query): void {
                $query->where(function ($active): void {
                    $active->where('status', BoostPurchaseRequestStatus::Approved)
                        ->where('ends_at', '>', now());
                })->orWhereIn('status', [
                    BoostPurchaseRequestStatus::PendingAdmin,
                    BoostPurchaseRequestStatus::PendingPayment,
                ]);
            })
            ->count();
    }

    public function findActiveCampaignForBusiness(
        BusinessInfo $business,
        string $tierKey,
        ?int $locationId = null,
    ): ?BoostPurchaseRequest {
        return BoostPurchaseRequest::query()
            ->where('business_info_id', $business->id)
            ->where('tier_key', $tierKey)
            ->when($locationId !== null, fn($query) => $query->where('location_id', $locationId))
            ->where('status', BoostPurchaseRequestStatus::Approved)
            ->where('ends_at', '>', now())
            ->latest('id')
            ->first();
    }

    public function remainingSlotsForTier(
        LgaBoost $lgaBoost,
        string $tierKey,
        ?int $excludeRequestId = null,
        ?int $forBusinessId = null,
    ): int {
        $tiers = collect($lgaBoost->tiers ?? []);
        $tier = $tiers->first(fn(array $row): bool => ($row['key'] ?? '') === $tierKey);

        if ($tier === null) {
            return 0;
        }

        $totalSlots = (int) ($tier['total_slots'] ?? 0);
        if ($totalSlots <= 0) {
            return 0;
        }

        $occupied = $this->countOccupiedSlotsForTier(
            (int) $lgaBoost->location_id,
            $tierKey,
            $excludeRequestId,
            $forBusinessId,
        );

        return max(0, $totalSlots - $occupied);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function enrichTiersWithSlotAvailability(LgaBoost $lgaBoost): array
    {
        return collect($lgaBoost->tiers ?? [])
            ->map(function (array $tier) use ($lgaBoost): array {
                $tierKey = (string) ($tier['key'] ?? '');
                $totalSlots = (int) ($tier['total_slots'] ?? 0);
                $occupied = $tierKey !== '' ? $this->countOccupiedSlotsForTier((int) $lgaBoost->location_id, $tierKey) : 0;
                $remaining = max(0, $totalSlots - $occupied);

                return array_merge($tier, [
                    'slots_occupied' => $occupied,
                    'slots_remaining' => $remaining,
                    'is_available' => $totalSlots > 0 && $remaining > 0,
                ]);
            })
            ->values()
            ->all();
    }

    public function createRequest(
        User $user,
        BusinessInfo $business,
        string $tierKey,
        int $durationDays,
        BoostPurchaseRequestStatus $status,
        ?Payment $payment = null,
        ?string $renewType = null,
        ?int $sourceCampaignId = null,
        ?int $targetLocationId = null,
        ?float $budgetAmount = null,
    ): BoostPurchaseRequest {
        $tierKey = $this->isDynamicTier($tierKey) ? $this->dynamicTierKey() : $tierKey;
        $renewType = in_array($renewType, ['extend', 'boost_again'], true) ? $renewType : null;
        $location = $this->resolveBoostTargetLocation($business, $targetLocationId, $sourceCampaignId, $renewType);
        $forBusinessId = $renewType !== null ? $business->id : null;

        if ($sourceCampaignId !== null) {
            $source = BoostPurchaseRequest::query()
                ->where('id', $sourceCampaignId)
                ->where('business_info_id', $business->id)
                ->first();

            if ($source === null) {
                throw new RuntimeException('The selected boost campaign was not found.');
            }

            if ($renewType === 'extend') {
                if ($source->status !== BoostPurchaseRequestStatus::Approved || ! $source->ends_at?->isFuture()) {
                    throw new RuntimeException('Only an active boost can be extended.');
                }
            } elseif ($renewType === 'boost_again') {
                $isExpired = $source->status === BoostPurchaseRequestStatus::Approved
                    && $source->ends_at instanceof Carbon
                    && $source->ends_at->isPast();

                if (! $isExpired) {
                    throw new RuntimeException('Boost again is only available after a campaign has expired.');
                }
            }
        }

        if ($this->isDynamicTier($tierKey)) {
            if ($budgetAmount === null) {
                throw new RuntimeException('Boost budget is required.');
            }
            $this->assertLocationHasBoostEnabled($location);
            $this->assertDynamicBoost($durationDays, $budgetAmount);
            $pricing = $this->resolveDynamicBoostPrice($budgetAmount, $durationDays);
        } else {
            $lgaBoost = $this->assertBoostAvailableForLocation($location, $tierKey, $durationDays, $forBusinessId, $renewType);
            $pricing = $this->resolveTierDurationPrice($lgaBoost, $tierKey, $durationDays);
        }

        $metadata = [
            'location_label' => $location->full_name,
            'state' => $location->state_name,
            'city' => $location->city_name,
            'lga' => $location->lga_name,
            'boost_model' => $this->isDynamicTier($tierKey) ? 'dynamic' : 'slot_tier',
        ];

        if ($this->isDynamicTier($tierKey) && $budgetAmount !== null) {
            $metadata['daily_budget'] = $pricing['daily_budget'];
        }

        if ($renewType !== null) {
            $metadata['renew_type'] = $renewType;
            $metadata['source_campaign_id'] = $sourceCampaignId;
        }

        $boostRequest = BoostPurchaseRequest::query()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'location_id' => $location->id,
            'payment_id' => $payment?->id,
            'tier_key' => $tierKey,
            'tier_label' => $pricing['tier_label'],
            'duration_days' => $durationDays,
            'amount' => $pricing['amount'],
            'currency' => config('subscription.currency', 'NGN'),
            'status' => $status,
            'metadata' => $metadata,
        ]);

        $this->syncBusinessLocationFromBoost($business, $location);

        return $boostRequest;
    }

    private function syncBusinessLocationFromBoost(BusinessInfo $business, Location $location): void
    {
        $previousLocationId = (int) $business->location_id;

        if ($previousLocationId === (int) $location->id) {
            return;
        }

        $business->update(['location_id' => $location->id]);
        $this->locationService->refreshVendorCountsAfterMove($previousLocationId, (int) $location->id);
    }

    private function resolveBoostTargetLocation(
        BusinessInfo $business,
        ?int $targetLocationId = null,
        ?int $sourceCampaignId = null,
        ?string $renewType = null,
    ): Location {
        if ($sourceCampaignId !== null && in_array($renewType, ['extend', 'boost_again'], true)) {
            $source = BoostPurchaseRequest::query()
                ->where('id', $sourceCampaignId)
                ->where('business_info_id', $business->id)
                ->with('location.lgaBoost')
                ->first();

            if ($source?->location !== null) {
                return $source->location;
            }
        }

        if ($targetLocationId !== null && $targetLocationId > 0) {
            $location = Location::query()->with('lgaBoost')->find($targetLocationId);

            if ($location === null) {
                throw new RuntimeException('The selected boost location is not valid.');
            }

            return $location;
        }

        $business->loadMissing('location.lgaBoost');

        if ($business->location === null) {
            throw new RuntimeException('Set your business location before requesting a boost.');
        }

        return $business->location;
    }

    public function markPaidAndQueueForAdmin(Payment $payment): ?BoostPurchaseRequest
    {
        $request = BoostPurchaseRequest::query()
            ->where('payment_id', $payment->id)
            ->first();

        if ($request === null) {
            return null;
        }

        $request->update([
            'status' => BoostPurchaseRequestStatus::PendingAdmin,
        ]);

        $request->loadMissing(['businessInfo', 'location']);

        if ($request->businessInfo !== null && $request->location !== null) {
            $this->syncBusinessLocationFromBoost($request->businessInfo, $request->location);
        }

        return $request->fresh();
    }

    /**
     * @return LengthAwarePaginator<BoostPurchaseRequest>
     */
    public function listForAdmin(?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return BoostPurchaseRequest::query()
            ->with([
                'businessInfo:id,business_name,user_id',
                'businessInfo.user:id,name,email',
                'location:id,lga_name,state_name,city_name',
            ])
            ->when($status !== null && $status !== '', fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, BoostPurchaseRequest>
     */
    public function pendingForAdmin(): Collection
    {
        return $this->waitingListForAdmin();
    }

    /**
     * Pending boost requests for admin waiting list (FIFO queue).
     *
     * @return Collection<int, BoostPurchaseRequest>
     */
    public function waitingListForAdmin(): Collection
    {
        $rows = BoostPurchaseRequest::query()
            ->whereIn('status', [
                BoostPurchaseRequestStatus::PendingAdmin,
                BoostPurchaseRequestStatus::PendingPayment,
            ])
            ->with([
                'businessInfo:id,business_name,user_id,category_id',
                'businessInfo.user:id,name,email',
                'businessInfo.category:id,name',
                'location:id,lga_name,state_name,city_name',
            ])
            ->orderByDesc('is_flagged')
            ->orderBy('created_at')
            ->get();

        $rows->each(function (BoostPurchaseRequest $request, int $index): void {
            $request->setAttribute('waiting_rank', $index + 1);
        });

        return $rows;
    }

    public function findForAdminDetail(int $id): BoostPurchaseRequest
    {
        $request = BoostPurchaseRequest::query()
            ->with([
                'businessInfo.user',
                'businessInfo.category',
                'location',
                'payment',
                'reviewer:id,name',
            ])
            ->findOrFail($id);

        $this->campaignAnalytics->attachCountsToCampaigns([$request]);

        return $request;
    }

    public function setFlagged(BoostPurchaseRequest $request, bool $flagged, ?string $note = null): BoostPurchaseRequest
    {
        if (! in_array($request->status, [
            BoostPurchaseRequestStatus::PendingAdmin,
            BoostPurchaseRequestStatus::PendingPayment,
        ], true)) {
            throw new RuntimeException('Only pending boost requests can be flagged.');
        }

        $request->update([
            'is_flagged' => $flagged,
            'admin_note' => $note ?? $request->admin_note,
        ]);

        return $request->fresh([
            'businessInfo:id,business_name,user_id,category_id',
            'businessInfo.user:id,name,email',
            'businessInfo.category:id,name',
            'location:id,lga_name,state_name,city_name',
        ]);
    }

    public function approve(BoostPurchaseRequest $request, Admin $admin, ?string $note = null): BoostPurchaseRequest
    {
        $request->loadMissing(['payment', 'location.lgaBoost']);

        if ($request->status === BoostPurchaseRequestStatus::PendingPayment) {
            if ($request->payment?->status !== PaymentStatus::Completed) {
                throw new RuntimeException('Payment must be completed before this boost can be assigned.');
            }
        } elseif ($request->status !== BoostPurchaseRequestStatus::PendingAdmin) {
            throw new RuntimeException('Only pending boost requests can be assigned.');
        }

        $renewType = (string) ($request->metadata['renew_type'] ?? '');
        $sourceCampaignId = isset($request->metadata['source_campaign_id'])
            ? (int) $request->metadata['source_campaign_id']
            : null;
        $extendSource = $renewType === 'extend' && $sourceCampaignId
            ? BoostPurchaseRequest::query()
            ->where('id', $sourceCampaignId)
            ->where('business_info_id', $request->business_info_id)
            ->first()
            : null;

        $lgaBoost = $request->location?->lgaBoost;
        if (
            $lgaBoost instanceof LgaBoost
            && ! ($extendSource?->ends_at?->isFuture())
            && ! $this->isDynamicTier($request->tier_key)
        ) {
            $tierLabel = collect($lgaBoost->tiers ?? [])->firstWhere('key', $request->tier_key)['label'] ?? $request->tier_label;
            if ($this->remainingSlotsForTier($lgaBoost, $request->tier_key, $request->id, $request->business_info_id) <= 0) {
                throw new RuntimeException(
                    "No slots available for {$tierLabel} in this LGA. Another vendor may have taken the last spot.",
                );
            }
        }

        return DB::transaction(function () use ($request, $admin, $note, $extendSource): BoostPurchaseRequest {
            $skipSlotIncrement = false;

            if ($extendSource?->ends_at?->isFuture()) {
                $startsAt = $extendSource->starts_at ?? Carbon::now();
                $endsAt = $extendSource->ends_at->copy()->addDays((int) $request->duration_days);
                $parentMetadata = $extendSource->metadata ?? [];
                $extensions = $parentMetadata['extensions'] ?? [];
                $extensions[] = [
                    'request_id' => $request->id,
                    'duration_days' => (int) $request->duration_days,
                    'amount' => (float) $request->amount,
                    'currency' => $request->currency,
                    'approved_at' => Carbon::now()->toIso8601String(),
                ];
                $extendSource->update([
                    'ends_at' => $endsAt,
                    'metadata' => array_merge($parentMetadata, ['extensions' => $extensions]),
                ]);
                $skipSlotIncrement = true;
            } else {
                $startsAt = Carbon::now();
                $endsAt = $startsAt->copy()->addDays((int) $request->duration_days);
            }

            $metadata = array_merge($request->metadata ?? [], [
                'activated_on_assign' => true,
                'activated_at' => $startsAt->toIso8601String(),
            ]);

            if ($skipSlotIncrement && $extendSource !== null) {
                $metadata['is_extension_record'] = true;
                $metadata['extension_parent_id'] = $extendSource->id;
            }

            $request->update([
                'status' => BoostPurchaseRequestStatus::Approved,
                'reviewed_by' => $admin->id,
                'admin_note' => $note,
                'reviewed_at' => Carbon::now(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'metadata' => $metadata,
            ]);

            $boost = Boost::query()->firstOrCreate(
                ['business_info_id' => $request->business_info_id],
                ['is_active' => false],
            );

            $boost->update([
                'is_active' => true,
                'activated_at' => $startsAt,
                'deactivated_at' => null,
            ]);

            if (! $skipSlotIncrement) {
                $location = Location::query()->with('lgaBoost')->find($request->location_id);
                if ($location?->lgaBoost instanceof LgaBoost) {
                    $lgaBoost = $location->lgaBoost;
                    if ($this->isDynamicTier($request->tier_key)) {
                        $lgaBoost->update([
                            'active_boosts' => $lgaBoost->active_boosts + 1,
                        ]);
                    } else {
                        $lgaBoost->update([
                            'slots_sold' => min($lgaBoost->total_slots, $lgaBoost->slots_sold + 1),
                            'slots_remaining' => max(0, $lgaBoost->slots_remaining - 1),
                            'active_boosts' => $lgaBoost->active_boosts + 1,
                        ]);
                    }
                }
            }

            return $request->fresh([
                'businessInfo:id,business_name',
                'location:id,lga_name,state_name,city_name',
            ]);
        });
    }

    public function reject(BoostPurchaseRequest $request, Admin $admin, ?string $note = null): BoostPurchaseRequest
    {
        if ($request->status !== BoostPurchaseRequestStatus::PendingAdmin) {
            throw new RuntimeException('Only pending boost requests can be rejected.');
        }

        $request->update([
            'status' => BoostPurchaseRequestStatus::Rejected,
            'reviewed_by' => $admin->id,
            'admin_note' => $note,
            'reviewed_at' => now(),
        ]);

        return $request->fresh();
    }

    /**
     * @return Collection<int, BoostPurchaseRequest>
     */
    public function listForVendor(BusinessInfo $business): Collection
    {
        $campaigns = BoostPurchaseRequest::query()
            ->where('business_info_id', $business->id)
            ->with([
                'location:id,lga_name,state_name,city_name',
                'businessInfo:id,business_name',
                'payment:id,status,purpose,amount,currency,tx_ref',
            ])
            ->orderByDesc('created_at')
            ->get();

        $this->campaignAnalytics->attachCountsToCampaigns($campaigns);

        return $campaigns;
    }

    /**
     * @return Collection<int, BoostPurchaseRequest>
     */
    public function listCampaignsForAdmin(?string $displayStatus = null): Collection
    {
        $campaigns = BoostPurchaseRequest::query()
            ->with([
                'businessInfo:id,business_name,user_id',
                'businessInfo.user:id,name,email',
                'location:id,lga_name,state_name,city_name',
            ])
            ->when($displayStatus === 'active', function ($query): void {
                $query->where('status', BoostPurchaseRequestStatus::Approved)
                    ->where('ends_at', '>', now());
            })
            ->when($displayStatus === 'expired', function ($query): void {
                $query->where('status', BoostPurchaseRequestStatus::Approved)
                    ->where('ends_at', '<=', now());
            })
            ->when($displayStatus === 'pending_admin', function ($query): void {
                $query->where('status', BoostPurchaseRequestStatus::PendingAdmin);
            })
            ->when($displayStatus === 'pending_payment', function ($query): void {
                $query->where('status', BoostPurchaseRequestStatus::PendingPayment);
            })
            ->when($displayStatus === 'rejected', function ($query): void {
                $query->where('status', BoostPurchaseRequestStatus::Rejected);
            })
            ->orderByDesc('created_at')
            ->get();

        $this->campaignAnalytics->attachCountsToCampaigns($campaigns);

        return $campaigns;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestOpenRequestForBusiness(BusinessInfo $business): ?array
    {
        $request = BoostPurchaseRequest::query()
            ->where('business_info_id', $business->id)
            ->whereIn('status', [
                BoostPurchaseRequestStatus::PendingPayment,
                BoostPurchaseRequestStatus::PendingAdmin,
            ])
            ->latest('id')
            ->first();

        if ($request === null) {
            return null;
        }

        $request->loadMissing('payment');

        return [
            'id' => $request->id,
            'tier_key' => $request->tier_key,
            'tier_label' => $request->tier_label,
            'duration_days' => $request->duration_days,
            'amount' => (float) $request->amount,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'payment_id' => $request->payment_id,
            'can_continue_payment' => $request->status === BoostPurchaseRequestStatus::PendingPayment
                && $request->payment?->status === PaymentStatus::Pending,
            'renew_type' => $request->metadata['renew_type'] ?? null,
        ];
    }
}
