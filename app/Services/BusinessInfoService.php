<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Enums\VerificationStatus;
use App\Http\Traits\FileManagementTrait;
use App\Models\Admin;
use App\Models\AdminVendorMessage;
use App\Models\Boost;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Support\BusinessSubcategoryResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BusinessInfoService
{
    use FileManagementTrait;

    /** Eager-load columns for vendor on public {@see BusinessInfoResource} payloads. */
    private const PUBLIC_VENDOR_USER_COLUMNS = 'user:id,name,email,phone,role,uuid';

    /*
    |--------------------------------------------------------------------------
    | Dependencies
    |--------------------------------------------------------------------------
    */

    public function __construct(
        private readonly LocationCatalogService $locationCatalog,
        private readonly ReviewService $reviewService,
        private readonly SubscriptionService $subscriptionService,
        private readonly VerificationService $verificationService,
        private readonly LocationService $locationService,
        private readonly BoostListingPriorityService $boostListingPriority,
        private readonly BusinessHoursService $businessHoursService,
        private readonly SocialAccountService $socialAccountService,
        private readonly PublicSearchQueryParser $publicSearchQueryParser,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public marketplace API
    |--------------------------------------------------------------------------
    | Used by: `App\Http\Controllers\Api\V1\Public\BusinessInfoController`
    | Routes: `routes/api/v1/public.php` — prefix `businesses` (home, featured, all, search, show).
    | Guest OK; optional `auth:api` user enables `is_favorite` on list/detail payloads.
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $validated
     */
    public function paginatePublicHomePage(array $validated, ?User $user, int $perPage): LengthAwarePaginator
    {
        $query = $this->publicBaseHomeQuery($user);
        $listingContext = $this->buildPublicBoostListingContext($validated);

        if (isset($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        $this->applyPublicSubcategoryFilter($query, $validated);

        $this->applyPublicLocationScope($query, $validated, $listingContext);

        $this->applyPublicGeoRadiusFilter($query, $validated);

        if (isset($validated['search'])) {
            $this->applyPublicSearchFilter($query, trim((string) $validated['search']));
        }

        if (isset($validated['featured']) && $validated['featured']) {
            $scopeLocationId = $listingContext['location_id'] ?? null;
            $query->whereHas('boostPurchaseRequests', function (Builder $campaignQuery) use ($scopeLocationId): void {
                $this->boostListingPriority->scopeActiveCampaign($campaignQuery, $scopeLocationId);
            });
        }

        $this->applyPublicBoostPriorityOrdering($query, $listingContext);

        return $query->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function getPublicFeaturedBusinesses(array $validated, ?User $user, int $limit): Collection
    {
        $base = $this->publicBaseFeaturedQuery($user);

        if (isset($validated['category_id'])) {
            $base->where('category_id', $validated['category_id']);
        }

        $this->applyPublicSubcategoryFilter($base, $validated);

        $listingContext = $this->buildPublicBoostListingContext($validated);
        $this->applyPublicLocationScope($base, $validated, $listingContext);

        $scopeLocationId = $listingContext['location_id'] ?? null;
        $boostedQuery = (clone $base)->whereHas('boostPurchaseRequests', function (Builder $campaignQuery) use ($scopeLocationId): void {
            $this->boostListingPriority->scopeActiveCampaign($campaignQuery, $scopeLocationId);
        });

        $this->applyPublicBoostPriorityOrdering($boostedQuery, $listingContext);

        $boosted = $boostedQuery->limit($limit)->get();

        if ($boosted->isNotEmpty()) {
            return $boosted;
        }

        $fallback = clone $base;
        $this->applyPublicBoostPriorityOrdering($fallback, $listingContext);

        return $fallback->limit($limit)->get();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function paginatePublicAll(array $validated, ?User $user, int $perPage): LengthAwarePaginator
    {
        $query = $this->publicBaseAllQuery($user);
        $listingContext = $this->buildPublicBoostListingContext($validated);

        if (isset($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        $this->applyPublicSubcategoryFilter($query, $validated);

        $this->applyPublicLocationScope($query, $validated, $listingContext);

        $this->applyPublicGeoRadiusFilter($query, $validated);

        if (isset($validated['verification_status'])) {
            $query->where('verification_status', $validated['verification_status']);
        }

        if (isset($validated['business_status'])) {
            $query->where('business_status', $validated['business_status']);
        }

        if (isset($validated['is_flagged'])) {
            $query->where('is_flagged', $validated['is_flagged']);
        }

        if (isset($validated['search'])) {
            $this->applyPublicSearchFilter($query, trim((string) $validated['search']));
        }

        $this->applyPublicBoostPriorityOrdering($query, $listingContext);

        return $query->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function paginatePublicSearch(array $validated, ?User $user, int $perPage): LengthAwarePaginator
    {
        $searchQuery = trim((string) $validated['query']);
        $listingContext = $this->buildPublicBoostListingContext($validated);

        $query = BusinessInfo::with(['category:id,name,subcategories', 'location:id,lga_name,state_name,city_name,latitude,longitude,formatted_address', 'businessHours', self::PUBLIC_VENDOR_USER_COLUMNS]);

        $this->applyPublicMarketplaceVisibility($query);
        $this->applyPublicSearchFilter($query, $searchQuery);

        $this->applyPublicListAggregates($query, $user);

        if (isset($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        $this->applyPublicSubcategoryFilter($query, $validated);

        $this->applyPublicLocationScope($query, $validated, $listingContext);

        $this->applyPublicGeoRadiusFilter($query, $validated);

        $this->applyPublicBoostPriorityOrdering($query, $listingContext);

        return $query->paginate($perPage);
    }

    /**
     * @return array{business: BusinessInfo, reviews_summary: array<string, mixed>}|null
     */
    public function getPublicPublishedBusinessDetail(int $businessId, ?User $user): ?array
    {
        $businessQuery = BusinessInfo::with([
            'category:id,name,subcategories',
            'location:id,lga_name,state_name,city_name,country_name,latitude,longitude,formatted_address',
            'businessHours',
            'catalogItems',
            self::PUBLIC_VENDOR_USER_COLUMNS,
        ]);

        $this->applyPublicFavoriteFlag($businessQuery, $user);
        $this->applyPublicFollowMetrics($businessQuery, $user);

        $business = $businessQuery
            ->where('id', $businessId)
            ->tap(fn(Builder $query) => $this->applyPublicMarketplaceVisibility($query))
            ->first();

        if ($business === null) {
            return null;
        }

        $reviewsSummary = $this->reviewService->getBusinessReviewsSummary($businessId);
        $business->setAttribute('average_rating', $reviewsSummary['average_rating']);
        $business->setAttribute('reviews_count', $reviewsSummary['total_reviews']);

        return [
            'business' => $business,
            'reviews_summary' => $reviewsSummary,
        ];
    }

    private function publicBaseHomeQuery(?User $user): Builder
    {
        $query = BusinessInfo::with(['category:id,name,subcategories', 'location:id,lga_name,state_name,city_name,country_name,latitude,longitude,formatted_address', 'businessHours', self::PUBLIC_VENDOR_USER_COLUMNS]);

        $this->applyPublicMarketplaceVisibility($query);
        $this->applyPublicListAggregates($query, $user);

        return $query;
    }

    private function publicBaseFeaturedQuery(?User $user): Builder
    {
        $query = BusinessInfo::with(['category:id,name,subcategories', 'location:id,lga_name,state_name,city_name,latitude,longitude,formatted_address', 'businessHours', self::PUBLIC_VENDOR_USER_COLUMNS]);

        $this->applyPublicMarketplaceVisibility($query);
        $query->orderBy('created_at', 'desc');

        $this->applyPublicListAggregates($query, $user);

        return $query;
    }

    private function publicBaseAllQuery(?User $user): Builder
    {
        $query = BusinessInfo::with(['category:id,name,subcategories', 'location:id,lga_name,state_name,city_name,country_name,latitude,longitude,formatted_address', 'businessHours', self::PUBLIC_VENDOR_USER_COLUMNS]);

        $this->applyPublicListAggregates($query, $user);

        return $query;
    }

    private function applyPublicListAggregates(Builder $query, ?User $user): void
    {
        $this->applyPublicApprovedReviewStats($query);
        $this->applyPublicFavoriteFlag($query, $user);
        $this->applyPublicFollowMetrics($query, $user);
    }

    /**
     * Public marketplace listings: active businesses only (verified badge is separate).
     */
    private function applyPublicMarketplaceVisibility(Builder $query): void
    {
        $query
            ->where('business_status', BusinessStatus::Active->value)
            ->where('is_flagged', false);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyPublicGeoRadiusFilter(Builder $query, array $validated): void
    {
        if (! isset($validated['lat'], $validated['lng'])) {
            return;
        }

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $radiusKm = isset($validated['radius_km']) ? (float) $validated['radius_km'] : 30.0;
        $radiusKm = max(1.0, min($radiusKm, 200.0));

        $query->whereHas('location', function (Builder $locationQuery) use ($lat, $lng, $radiusKm): void {
            $locationQuery
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereRaw(
                    '(6371 * acos(LEAST(1, GREATEST(-1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))) <= ?',
                    [$lat, $lng, $lat, $radiusKm],
                );
        });
    }

    private function applyPublicSearchFilter(Builder $query, string $searchTerm): void
    {
        if ($searchTerm === '') {
            return;
        }

        $parsed = $this->publicSearchQueryParser->parse($searchTerm);

        if ($parsed->hasLocationOnly()) {
            return;
        }

        if ($parsed->serviceTermGroups !== []) {
            foreach ($parsed->serviceTermGroups as $termGroup) {
                $query->where(function (Builder $groupQuery) use ($termGroup): void {
                    foreach ($termGroup as $term) {
                        $groupQuery->orWhere(function (Builder $termQuery) use ($term): void {
                            $this->appendPublicSearchTermClauses($termQuery, $term, includeLocationFields: false);
                        });
                    }
                });
            }

            return;
        }

        if (! $parsed->hasParsedIntent()) {
            $query->where(function (Builder $innerQuery) use ($searchTerm): void {
                $this->appendPublicSearchTermClauses($innerQuery, $searchTerm, includeLocationFields: true);
            });
        }
    }

    private function appendPublicSearchTermClauses(
        Builder $query,
        string $term,
        bool $includeLocationFields,
    ): void {
        $like = '%' . $term . '%';

        $query->where('business_name', 'like', $like)
            ->orWhere('business_description', 'like', $like)
            ->orWhere('subcategory', 'like', $like)
            ->orWhereRaw('CAST(services_offered AS CHAR) LIKE ?', [$like])
            ->orWhereHas('category', function (Builder $categoryQuery) use ($like): void {
                $categoryQuery->where('name', 'like', $like);
            });

        if ($includeLocationFields) {
            $query->orWhereHas('location', function (Builder $locationQuery) use ($like): void {
                $locationQuery->where('lga_name', 'like', $like)
                    ->orWhere('state_name', 'like', $like)
                    ->orWhere('city_name', 'like', $like)
                    ->orWhere('country_name', 'like', $like);
            });
        }
    }

    private function applyPublicApprovedReviewStats(Builder $query): void
    {
        $query->withAvg(['reviews as average_rating' => function (Builder $reviewQuery): void {
            $reviewQuery->where('is_approved', true);
        }], 'rating')
            ->withCount(['reviews as reviews_count' => function (Builder $reviewQuery): void {
                $reviewQuery->where('is_approved', true);
            }]);
    }

    private function applyPublicFavoriteFlag(Builder|QueryBuilder $query, ?User $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        $query->withExists([
            'favorites as is_favorite' => function (Builder $favoriteQuery) use ($user): void {
                $favoriteQuery->where('user_id', $user->id);
            },
        ]);
    }

    private function applyPublicFollowMetrics(Builder|QueryBuilder $query, ?User $user): void
    {
        $query->withCount(['followerLinks as followers_count']);

        if (! $user instanceof User) {
            return;
        }

        $query->withExists([
            'followerLinks as is_following' => function (Builder $followQuery) use ($user): void {
                $followQuery->where('follower_id', $user->id);
            },
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Vendor (authenticated vendor business profile)
    |--------------------------------------------------------------------------
    | Used by: `App\Http\Controllers\Api\V1\BusinessInfoController` (vendor routes)
    |--------------------------------------------------------------------------
    */

    public function userAlreadyHasProfile(User $user): bool
    {
        return BusinessInfo::query()->where('user_id', $user->id)->exists();
    }

    /**
     * Create a minimal free business profile so a customer can switch to vendor mode
     * without completing the legacy plan-form onboarding first.
     */
    public function createFreeTemplateForUser(User $user): BusinessInfo
    {
        if ($this->userAlreadyHasProfile($user)) {
            throw new \RuntimeException('A business profile already exists for this account.');
        }

        return $this->createTemplateBusinessForUser($user, null, 0);
    }

    public function createAdditionalBusinessForUser(User $user, ?string $name = null): BusinessInfo
    {
        $existingCount = BusinessInfo::query()->where('user_id', $user->id)->count();
        $maxSort = (int) (BusinessInfo::query()->where('user_id', $user->id)->max('sort_order') ?? 0);
        $businessName = $this->resolveAdditionalBusinessName($user, $name, $existingCount);

        $business = $this->createTemplateBusinessForUser($user, $businessName, $maxSort + 1);
        $this->setActiveBusinessForUser($user, (int) $business->id);

        return $business->fresh(['subscription', 'businessHours']);
    }

    public function assertUserOwnsBusiness(User $user, int $businessId): BusinessInfo
    {
        $business = $this->businessQueryForUser($user)
            ->where('id', $businessId)
            ->first();

        if ($business === null) {
            throw ValidationException::withMessages([
                'business_id' => ['Business not found for this account.'],
            ]);
        }

        return $business;
    }

    public function setActiveBusinessForUser(User $user, int $businessId): User
    {
        $this->assertUserOwnsBusiness($user, $businessId);

        $settings = is_array($user->settings) ? $user->settings : [];
        $settings['active_business_id'] = $businessId;
        $user->forceFill(['settings' => $settings])->save();

        return $user->fresh();
    }

    public function deleteForUser(User $user, int $businessId): void
    {
        $business = $this->assertUserOwnsBusiness($user, $businessId);
        $locationId = (int) $business->location_id;
        $deletedId = (int) $business->id;

        DB::transaction(function () use ($user, $business, $locationId, $deletedId): void {
            $business->delete();

            $this->locationService->refreshVendorCount($locationId);

            $settings = is_array($user->settings) ? $user->settings : [];
            if ((int) ($settings['active_business_id'] ?? 0) === $deletedId) {
                $nextBusinessId = BusinessInfo::query()
                    ->where('user_id', $user->id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->value('id');

                if ($nextBusinessId !== null) {
                    $settings['active_business_id'] = (int) $nextBusinessId;
                } else {
                    unset($settings['active_business_id']);
                }

                $user->forceFill(['settings' => $settings])->save();
            }
        });
    }

    public function resolveBusinessFromRequest(Request $request): BusinessInfo
    {
        /** @var User|null $user */
        $user = $request->user('api');
        if ($user === null) {
            throw ValidationException::withMessages([
                'auth' => ['Unauthenticated.'],
            ]);
        }

        $businessId = $request->input('business_id');
        if ($businessId !== null && $businessId !== '') {
            return $this->assertUserOwnsBusiness($user, (int) $businessId);
        }

        $business = $this->findForUser($user);
        if ($business === null) {
            throw ValidationException::withMessages([
                'business_id' => ['No business profile found for this account.'],
            ]);
        }

        return $business;
    }

    /**
     * @return Builder<BusinessInfo>
     */
    private function businessQueryForUser(User $user): Builder
    {
        return BusinessInfo::query()
            ->where('user_id', $user->id)
            ->with([
                'category:id,name,subcategories',
                'location:id,lga_name,state_name,city_name,country_name,latitude,longitude,formatted_address',
                'boost:id,business_info_id,is_active,activated_at,deactivated_at',
                'subscription',
                'businessHours',
            ]);
    }

    private function resolveAdditionalBusinessName(User $user, ?string $name, int $existingCount): string
    {
        if ($name !== null && trim($name) !== '') {
            return trim($name);
        }

        if ($existingCount === 0) {
            return trim((string) $user->name) !== ''
                ? trim((string) $user->name) . ' Business'
                : 'My Business';
        }

        return 'Business ' . ($existingCount + 1);
    }

    private function createTemplateBusinessForUser(User $user, ?string $businessName, int $sortOrder): BusinessInfo
    {
        if ($businessName === null || trim($businessName) === '') {
            $businessName = trim((string) $user->name) !== ''
                ? trim((string) $user->name) . ' Business'
                : 'My Business';
        }

        $phone = trim((string) ($user->phone ?? ''));
        if ($phone === '') {
            $phone = '+2348000000000';
        }

        return DB::transaction(function () use ($user, $businessName, $phone, $sortOrder): BusinessInfo {
            $business = BusinessInfo::query()->create([
                'sort_order' => $sortOrder,
                'location_id' => null,
                'user_id' => $user->id,
                'category_id' => null,
                'subcategory' => null,
                'business_name' => $businessName,
                'street_address' => null,
                'business_description' => 'Tell customers about your business and the services you offer.',
                'services_offered' => [],
                'phone' => $phone,
                'whatsapp' => null,
                'website' => null,
                'social_accounts' => null,
                'logo_path' => null,
                'cover_photo_paths' => null,
                'verification_status' => VerificationStatus::None,
                'is_flagged' => false,
                'business_status' => BusinessStatus::Active,
            ]);

            $this->businessHoursService->seedDefaultsForBusiness($business);

            $this->subscriptionService->createForBusiness(
                $business,
                SubscriptionPlan::Free,
                SubscriptionStatus::Active,
            );

            return $business->load(['subscription', 'businessHours']);
        });
    }

    /**
     * @param  list<string>  $services
     * @param  array<int, UploadedFile>  $coverPhotos
     * @param  array<int, array<string, mixed>>|null  $businessHours
     */
    public function createForUser(
        User $user,
        int $categoryId,
        ?string $subcategory,
        int $locationId,
        string $businessName,
        ?string $streetAddress,
        string $businessDescription,
        array $services,
        string $phone,
        ?string $whatsapp,
        ?string $website,
        ?array $socialAccounts,
        UploadedFile $logo,
        array $coverPhotos,
        SubscriptionPlan $subscriptionPlan = SubscriptionPlan::Free,
        ?array $businessHours = null,
    ): BusinessInfo {
        if (! Location::where('id', $locationId)->exists()) {
            throw new \InvalidArgumentException('Invalid location ID.');
        }

        if ($this->userAlreadyHasProfile($user)) {
            throw new \RuntimeException('A business profile already exists for this account.');
        }

        $basePath = 'businesses/' . $user->id;
        $logoFolder = $basePath . '/logo';
        $coverFolder = $basePath . '/covers';
        $logoPath = null;
        $coverPaths = [];

        try {
            $logoPath = $this->handleFileUpload($logo, $logoFolder, $businessName . ' logo');

            foreach ($coverPhotos as $file) {
                $coverPaths[] = $this->handleFileUpload($file, $coverFolder, $businessName . ' cover');
            }

            $isPremium = $subscriptionPlan === SubscriptionPlan::Premium;

            $normalizedHours = $this->businessHoursService->normalizeInput($businessHours);
            $normalizedSocialAccounts = $this->socialAccountService->normalizeInput($socialAccounts);

            $normalizedStreetAddress = $streetAddress !== null && trim($streetAddress) !== ''
                ? trim($streetAddress)
                : null;

            $resolvedSubcategory = BusinessSubcategoryResolver::resolve($subcategory, $categoryId, $services);

            return DB::transaction(function () use (
                $user,
                $categoryId,
                $resolvedSubcategory,
                $locationId,
                $businessName,
                $normalizedStreetAddress,
                $businessDescription,
                $services,
                $phone,
                $whatsapp,
                $website,
                $normalizedSocialAccounts,
                $logoPath,
                $coverPaths,
                $subscriptionPlan,
                $isPremium,
                $normalizedHours,
            ): BusinessInfo {
                $business = BusinessInfo::query()->create([
                    'location_id' => $locationId,
                    'user_id' => $user->id,
                    'category_id' => $categoryId,
                    'subcategory' => $resolvedSubcategory,
                    'business_name' => $businessName,
                    'street_address' => $normalizedStreetAddress,
                    'business_description' => $businessDescription,
                    'services_offered' => $services,
                    'phone' => $phone,
                    'whatsapp' => $whatsapp,
                    'website' => $website,
                    'social_accounts' => $normalizedSocialAccounts,
                    'logo_path' => $logoPath,
                    'cover_photo_paths' => $coverPaths,
                    'verification_status' => VerificationStatus::None,
                    'is_flagged' => false,
                    'business_status' => $isPremium ? BusinessStatus::Inactive : BusinessStatus::Active,
                ]);

                $this->businessHoursService->syncForBusiness($business, $normalizedHours);

                $this->subscriptionService->createForBusiness(
                    $business,
                    $subscriptionPlan,
                    $isPremium ? SubscriptionStatus::PendingPayment : SubscriptionStatus::Active,
                );

                $this->locationService->refreshVendorCount($locationId);

                if ($user->role !== 'vendor') {
                    $user->forceFill(['role' => 'vendor'])->save();
                }

                $this->setActiveBusinessForUser($user, (int) $business->id);

                return $business->load(['subscription', 'businessHours']);
            });
        } catch (Throwable $e) {
            $this->fileDelete($logoPath);
            foreach ($coverPaths as $path) {
                $this->fileDelete($path);
            }

            throw $e;
        }
    }

    public function findForUser(User $user, ?int $businessId = null): ?BusinessInfo
    {
        if ($businessId !== null) {
            return $this->businessQueryForUser($user)
                ->where('id', $businessId)
                ->first();
        }

        $settings = is_array($user->settings) ? $user->settings : [];
        $activeId = (int) ($settings['active_business_id'] ?? 0);
        if ($activeId > 0) {
            $active = $this->businessQueryForUser($user)
                ->where('id', $activeId)
                ->first();
            if ($active !== null) {
                return $active;
            }
        }

        return $this->businessQueryForUser($user)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * When a vendor purchases boost for an LGA, align their business profile location with that target.
     */
    public function syncLocationFromBoostTarget(BusinessInfo $business, int $locationId): BusinessInfo
    {
        $previousLocationId = (int) $business->location_id;

        if ($previousLocationId === $locationId) {
            return $business->loadMissing('location');
        }

        if (! Location::whereKey($locationId)->exists()) {
            throw new \InvalidArgumentException('Invalid boost target location.');
        }

        $business->update(['location_id' => $locationId]);
        $this->locationService->refreshVendorCountsAfterMove($previousLocationId, $locationId);

        return $business->fresh(['location', 'category', 'subscription', 'boost']);
    }

    /**
     * @param  list<string>  $services
     * @param  array<int, UploadedFile>  $coverPhotos
     */
    /**
     * @param  list<string>  $services
     * @param  array<int, UploadedFile>  $coverPhotos
     * @param  array<int, array<string, mixed>>|null  $businessHours
     * @param  list<string>|null  $keepCoverPaths
     */
    public function updateForUser(
        User $user,
        ?int $categoryId,
        ?string $subcategory,
        ?int $locationId,
        string $businessName,
        ?string $streetAddress,
        string $businessDescription,
        array $services,
        string $phone,
        ?string $whatsapp,
        ?string $website,
        ?array $socialAccounts,
        ?UploadedFile $logo,
        array $coverPhotos,
        ?array $businessHours = null,
        bool $streetAddressProvided = false,
        bool $subcategoryProvided = true,
        ?array $keepCoverPaths = null,
        ?int $businessId = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $googlePlaceId = null,
        bool $coordinatesProvided = false,
        ?string $locationNarrative = null,
        bool $locationNarrativeProvided = false,
    ): BusinessInfo {
        $business = $businessId !== null
            ? $this->assertUserOwnsBusiness($user, $businessId)
            : $this->findForUser($user);
        if ($business === null) {
            throw new \RuntimeException('No business profile found for this account.');
        }

        if ($categoryId !== null && $categoryId > 0 && ! \App\Models\Category::whereKey($categoryId)->exists()) {
            throw new \InvalidArgumentException('Invalid category ID.');
        }

        if ($locationId !== null && $locationId > 0 && ! Location::where('id', $locationId)->exists()) {
            throw new \InvalidArgumentException('Invalid location ID.');
        }

        $finalCategoryId = ($categoryId !== null && $categoryId > 0)
            ? $categoryId
            : ($business->category_id !== null ? (int) $business->category_id : null);

        $finalLocationId = ($locationId !== null && $locationId > 0)
            ? $locationId
            : ($business->location_id !== null ? (int) $business->location_id : null);

        $basePath = 'businesses/' . $user->id;
        $logoFolder = $basePath . '/logo';
        $coverFolder = $basePath . '/covers';

        $oldLogoPath = $business->logo_path;
        $oldCoverPaths = is_array($business->cover_photo_paths) ? $business->cover_photo_paths : [];

        $newLogoPath = null;
        $newCoverPaths = [];

        try {
            $finalLogoPath = $business->logo_path;
            if ($logo !== null) {
                $newLogoPath = $this->handleFileUpload($logo, $logoFolder, $businessName . ' logo');
                $finalLogoPath = $newLogoPath;
            }

            $finalCoverPaths = $oldCoverPaths;
            $coverGalleryUpdated = $keepCoverPaths !== null || $coverPhotos !== [];

            if ($coverGalleryUpdated) {
                $keptPaths = [];
                if ($keepCoverPaths !== null) {
                    foreach ($keepCoverPaths as $path) {
                        if (! is_string($path) || trim($path) === '') {
                            continue;
                        }
                        $normalizedPath = trim($path);
                        if (in_array($normalizedPath, $oldCoverPaths, true)) {
                            $keptPaths[] = $normalizedPath;
                        }
                    }
                } elseif ($coverPhotos === []) {
                    $keptPaths = $oldCoverPaths;
                }

                foreach ($coverPhotos as $file) {
                    $newCoverPaths[] = $this->handleFileUpload($file, $coverFolder, $businessName . ' cover');
                }

                $finalCoverPaths = array_values(array_merge($keptPaths, $newCoverPaths));
            }

            $maxCoverPhotos = $this->subscriptionService->maxCoverPhotos($business);
            if (count($finalCoverPaths) > $maxCoverPhotos) {
                throw new \InvalidArgumentException("You can have up to {$maxCoverPhotos} gallery photos on your current plan.");
            }

            $previousLocationId = $business->location_id !== null ? (int) $business->location_id : 0;

            $categoryForSubcategory = $finalCategoryId ?? 0;
            $resolvedSubcategory = BusinessSubcategoryResolver::resolve($subcategory, $categoryForSubcategory, $services);
            if (! $subcategoryProvided) {
                $resolvedSubcategory = $business->subcategory;
            } elseif ($resolvedSubcategory === null) {
                $resolvedSubcategory = BusinessSubcategoryResolver::resolve(
                    $business->subcategory,
                    $categoryForSubcategory,
                    $services,
                );
            }

            $normalizedStreetAddress = $streetAddress !== null && trim($streetAddress) !== ''
                ? trim($streetAddress)
                : null;

            $normalizedLocationNarrative = $locationNarrative !== null && trim($locationNarrative) !== ''
                ? trim($locationNarrative)
                : null;

            $majorChange = $business->business_name !== $businessName
                || ($categoryId !== null && $categoryId > 0 && (int) ($business->category_id ?? 0) !== $categoryId)
                || $business->subcategory !== $resolvedSubcategory
                || ($locationId !== null && $locationId > 0 && (int) ($business->location_id ?? 0) !== $locationId);

            $normalizedHours = $businessHours !== null
                ? $this->businessHoursService->normalizeInput($businessHours)
                : null;
            $normalizedSocialAccounts = $this->socialAccountService->normalizeInput($socialAccounts);

            $business = DB::transaction(function () use (
                $business,
                $finalCategoryId,
                $resolvedSubcategory,
                $finalLocationId,
                $businessName,
                $normalizedStreetAddress,
                $streetAddressProvided,
                $businessDescription,
                $services,
                $phone,
                $whatsapp,
                $website,
                $normalizedSocialAccounts,
                $finalLogoPath,
                $finalCoverPaths,
                $majorChange,
                $normalizedHours,
                $latitude,
                $longitude,
                $googlePlaceId,
                $coordinatesProvided,
                $locationNarrative,
                $locationNarrativeProvided,
                $normalizedLocationNarrative,
            ): BusinessInfo {
                $payload = [
                    'location_id' => $finalLocationId,
                    'category_id' => $finalCategoryId,
                    'subcategory' => $resolvedSubcategory,
                    'business_name' => $businessName,
                    'business_description' => $businessDescription,
                    'services_offered' => $services,
                    'phone' => $phone,
                    'whatsapp' => $whatsapp,
                    'website' => $website,
                    'social_accounts' => $normalizedSocialAccounts,
                    'logo_path' => $finalLogoPath,
                    'cover_photo_paths' => $finalCoverPaths,
                ];

                if ($streetAddressProvided) {
                    $payload['street_address'] = $normalizedStreetAddress;
                }

                if ($coordinatesProvided) {
                    $payload['latitude'] = $latitude;
                    $payload['longitude'] = $longitude;
                    $payload['google_place_id'] = $googlePlaceId;
                }

                if ($locationNarrativeProvided) {
                    $payload['location_narrative'] = $normalizedLocationNarrative;
                }

                $business->update($payload);

                if ($normalizedHours !== null) {
                    $this->businessHoursService->syncForBusiness($business, $normalizedHours);
                }

                if ($majorChange) {
                    $this->verificationService->revokeVerificationForMajorBusinessChange(
                        $business,
                        'Verification badge removed because business name, category, or location was changed. Please complete verification again.',
                    );
                }

                return $business->refresh()->load('businessHours');
            });

            if ($previousLocationId > 0 && $finalLocationId !== null && $previousLocationId !== $finalLocationId) {
                $this->locationService->refreshVendorCountsAfterMove($previousLocationId, $finalLocationId);
            }

            if ($newLogoPath !== null && $oldLogoPath !== null && $oldLogoPath !== '') {
                $this->fileDelete($oldLogoPath);
            }

            if ($coverGalleryUpdated) {
                foreach ($oldCoverPaths as $path) {
                    if (! is_string($path) || $path === '') {
                        continue;
                    }
                    if (! in_array($path, $finalCoverPaths, true)) {
                        $this->fileDelete($path);
                    }
                }
            }

            return $business;
        } catch (Throwable $e) {
            $this->fileDelete($newLogoPath);
            foreach ($newCoverPaths as $path) {
                $this->fileDelete($path);
            }

            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    | Used by: `App\Http\Controllers\Api\V1\Admin\BusinessInfoController`
    |--------------------------------------------------------------------------
    */

    /**
     * Base query for admin business directory (filters only, no eager loads).
     */
    private function adminBusinessListBaseQuery(
        ?string $search = null,
        ?string $verificationStatus = null,
        ?string $businessStatus = null,
        ?int $categoryId = null,
        ?string $boostStatus = null,
    ): Builder {
        return BusinessInfo::query()
            ->when($search !== null && trim($search) !== '', function ($query) use ($search): void {
                $term = trim($search);
                $query->where(function ($innerQuery) use ($term): void {
                    $innerQuery->where('business_name', 'like', "%{$term}%")
                        ->orWhereHas('location', function ($locationQuery) use ($term): void {
                            $locationQuery->where('lga_name', 'like', "%{$term}%")
                                ->orWhere('state_name', 'like', "%{$term}%")
                                ->orWhere('city_name', 'like', "%{$term}%");
                        })
                        ->orWhereHas('user', function ($userQuery) use ($term): void {
                            $userQuery->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%")
                                ->orWhere('phone', 'like', "%{$term}%");
                        })
                        ->orWhereHas('category', function ($categoryQuery) use ($term): void {
                            $categoryQuery->where('name', 'like', "%{$term}%");
                        });
                });
            })
            ->when($verificationStatus !== null, function ($query) use ($verificationStatus): void {
                $query->where('verification_status', $verificationStatus);
            })
            ->when($businessStatus !== null, function ($query) use ($businessStatus): void {
                $query->where('business_status', $businessStatus);
            })
            ->when($categoryId !== null, function ($query) use ($categoryId): void {
                $query->where('category_id', $categoryId);
            })
            ->when($boostStatus === 'active', function ($query): void {
                $query->whereHas('boost', function ($boostQuery): void {
                    $boostQuery->where('is_active', true);
                });
            })
            ->when($boostStatus === 'none', function ($query): void {
                $query->where(function ($innerQuery): void {
                    $innerQuery->whereDoesntHave('boost')
                        ->orWhereHas('boost', function ($boostQuery): void {
                            $boostQuery->where('is_active', false);
                        });
                });
            })
            ->latest();
    }

    /**
     * @return array{
     *     verification_statuses: list<array{value: string, label: string}>,
     *     business_statuses: list<array{value: string, label: string}>,
     *     boost_statuses: list<array{value: string, label: string}>,
     *     categories: list<array{id: int, name: string}>
     * }
     */
    public function getAdminBusinessFilterOptions(): array
    {
        $verificationStatuses = array_map(
            fn(VerificationStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            VerificationStatus::cases(),
        );

        $businessStatuses = array_map(
            fn(BusinessStatus $status): array => [
                'value' => $status->value,
                'label' => ucfirst($status->value),
            ],
            BusinessStatus::cases(),
        );

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->values()
            ->all();

        return [
            'verification_statuses' => $verificationStatuses,
            'business_statuses' => $businessStatuses,
            'boost_statuses' => [
                ['value' => 'active', 'label' => 'Active boost'],
                ['value' => 'none', 'label' => 'No boost'],
            ],
            'categories' => $categories,
        ];
    }

    /**
     * @return array{total: int, pending_verification: int, approved_verification: int, free_plan: int, premium_plan: int}
     */
    public function getAdminBusinessListSummary(
        ?string $search = null,
        ?string $verificationStatus = null,
        ?string $businessStatus = null,
        ?int $categoryId = null,
        ?string $boostStatus = null,
    ): array {
        $base = $this->adminBusinessListBaseQuery($search, $verificationStatus, $businessStatus, $categoryId, $boostStatus);

        return [
            'total' => (clone $base)->count(),
            'pending_verification' => (clone $base)->where('verification_status', VerificationStatus::Pending)->count(),
            'approved_verification' => (clone $base)->where('verification_status', VerificationStatus::Approved)->count(),
            'free_plan' => (clone $base)->whereHas('subscription', function (Builder $query): void {
                $query->where('plan', SubscriptionPlan::Free->value)
                    ->orWhere(function (Builder $inner): void {
                        $inner->where('plan', SubscriptionPlan::Premium->value)
                            ->where('status', SubscriptionStatus::PendingPayment->value);
                    });
            })->count(),
            'premium_plan' => (clone $base)
                ->whereHas('subscription', function (Builder $query): void {
                    $query->where('plan', SubscriptionPlan::Premium->value)
                        ->where('status', SubscriptionStatus::Active->value);
                })
                ->count(),
        ];
    }

    public function paginateForAdmin(
        ?string $search = null,
        ?string $verificationStatus = null,
        int $perPage = 10,
        ?string $businessStatus = null,
        ?int $categoryId = null,
        ?int $page = null,
        ?string $boostStatus = null,
    ): LengthAwarePaginator {
        $page = max(1, $page ?? 1);

        return $this->adminBusinessListBaseQuery($search, $verificationStatus, $businessStatus, $categoryId, $boostStatus)
            ->with([
                'category:id,name,subcategories',
                'location:id,lga_name,state_name,city_name,country_name,latitude,longitude,formatted_address',
                'user:id,first_name,last_name,name,email,phone,role',
                'boost:id,business_info_id,is_active,activated_at,deactivated_at',
            ])
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getBusinessInfoByIdForAdmin(int $businessInfoId): BusinessInfo
    {
        return BusinessInfo::query()
            ->with([
                'category:id,name,subcategories',
                'location:id,lga_name,state_name,city_name,country_name,latitude,longitude,formatted_address',
                'user:id,first_name,last_name,name,email,phone,role',
                'verifiedBy:id,name,email,phone,role',
                'boost:id,business_info_id,is_active,activated_at,deactivated_at',
                'messages' => fn($query) => $query
                    ->with(['admin:id,name,email', 'vendor:id,name,email'])
                    ->latest(),
            ])
            ->findOrFail($businessInfoId);
    }

    public function createAdminMessage(BusinessInfo $businessInfo, Admin $admin, string $message): AdminVendorMessage
    {
        $businessInfo->loadMissing('user');

        $vendor = $businessInfo->user;

        if ($vendor === null) {
            throw ValidationException::withMessages([
                'business_info_id' => ['This business has no vendor account.'],
            ]);
        }

        $record = $businessInfo->messages()->create([
            'admin_id' => $admin->id,
            'vendor_id' => $vendor->id,
            'message' => $message,
        ]);

        return $record->load(['admin:id,name,email', 'vendor:id,name,email']);
    }

    public function changeBusinessStatus(BusinessInfo $businessInfo, BusinessStatus $status): BusinessInfo
    {
        $businessInfo->update([
            'business_status' => $status,
        ]);

        return $businessInfo->fresh([
            'category:id,name,subcategories',
            'location:id,lga_name,state_name,city_name,country_name,latitude,longitude,formatted_address',
            'user:id,first_name,last_name,name,email,phone,role',
        ]);
    }

    public function setBoostStatusForVendor(User $user, bool $isActive, ?int $businessId = null): Boost
    {
        $business = $businessId !== null
            ? $this->assertUserOwnsBusiness($user, $businessId)
            : $this->findForUser($user);

        if ($business === null) {
            throw new \RuntimeException('No business profile found for this account.');
        }

        if ($isActive && ! $this->subscriptionService->hasActivePremium($business)) {
            throw new \RuntimeException('An active premium subscription is required to boost your profile.');
        }

        $boost = Boost::query()->firstOrCreate(
            ['business_info_id' => $business->id],
            ['is_active' => false],
        );

        $boost->update([
            'is_active' => $isActive,
            'activated_at' => $isActive ? now() : $boost->activated_at,
            'deactivated_at' => $isActive ? null : now(),
        ]);

        return $boost->fresh();
    }

    /**
     * Listing context for boost tier ordering (top_1 → top_5 → top_10 → others).
     *
     * @param  array<string, mixed>  $validated
     * @return array{location_id?: int, location_ids?: list<int>, resolved_from_search?: bool}
     */
    private function buildPublicBoostListingContext(array $validated): array
    {
        if (isset($validated['location_id']) && (int) $validated['location_id'] > 0) {
            return ['location_id' => (int) $validated['location_id']];
        }

        $search = trim((string) ($validated['search'] ?? $validated['query'] ?? ''));
        if ($search === '') {
            return [];
        }

        $parsed = $this->publicSearchQueryParser->parse($search);
        if ($parsed->locationIds === []) {
            return [];
        }

        if (count($parsed->locationIds) === 1) {
            return [
                'location_id' => $parsed->locationIds[0],
                'resolved_from_search' => true,
            ];
        }

        return [
            'location_ids' => $parsed->locationIds,
            'resolved_from_search' => true,
        ];
    }

    /**
     * Restrict listings to a single LGA/city when the user filters or searches by location name.
     *
     * @param  array<string, mixed>  $validated
     * @param  array{location_id?: int, location_ids?: list<int>, resolved_from_search?: bool}  $listingContext
     */
    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyPublicSubcategoryFilter(Builder $query, array $validated): void
    {
        if (! isset($validated['subcategory'])) {
            return;
        }

        $subcategory = trim((string) $validated['subcategory']);
        if ($subcategory === '') {
            return;
        }

        $query->where('subcategory', $subcategory);
    }

    private function applyPublicLocationScope(Builder $query, array $validated, array $listingContext = []): void
    {
        if (isset($validated['location_id']) && (int) $validated['location_id'] > 0) {
            $query->where('location_id', (int) $validated['location_id']);

            return;
        }

        if (! ($listingContext['resolved_from_search'] ?? false)) {
            return;
        }

        if (isset($listingContext['location_id']) && (int) $listingContext['location_id'] > 0) {
            $query->where('location_id', (int) $listingContext['location_id']);

            return;
        }

        if (! empty($listingContext['location_ids'])) {
            $query->whereIn('location_id', $listingContext['location_ids']);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function applyPublicBoostPriorityOrdering(Builder $query, array $context = []): void
    {
        $this->boostListingPriority->applyToQuery($query, $context);
    }
}
