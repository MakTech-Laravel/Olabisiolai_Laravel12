<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Enums\UploadableType;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessInfo;
use App\Models\User;
use App\Support\CatalogPricing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BusinessCatalogService
{
    public const MAX_ITEMS_PER_BUSINESS = 50;

    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    /**
     * @return Collection<int, BusinessCatalogItem>
     */
    public function listForBusiness(BusinessInfo $business): Collection
    {
        return $business->catalogItems()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Full Catalog-tab discovery feed (premium vendors, with optional filters).
     * Homepage strips use the same endpoint with a smaller `per_page`.
     *
     * @param  array{category_id?: int|null, city?: string|null, type?: string|null, search?: string|null}  $filters
     */
    public function paginateDiscoveryFeed(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $query = $this->discoveryBaseQuery();

        if (! empty($filters['category_id'])) {
            $query->whereHas('businessInfo', function (Builder $business) use ($filters): void {
                $business->where('category_id', (int) $filters['category_id']);
            });
        }

        if (! empty($filters['city'])) {
            $city = trim((string) $filters['city']);
            $query->whereHas('businessInfo.location', function (Builder $location) use ($city): void {
                $location->where('city_name', 'like', "%{$city}%")
                    ->orWhere('lga_name', 'like', "%{$city}%")
                    ->orWhere('state_name', 'like', "%{$city}%");
            });
        }

        if (! empty($filters['type'])) {
            $type = $this->normalizeType($filters['type']);
            $query->where('type', $type);
        }

        if (! empty($filters['search'])) {
            $keyword = trim((string) $filters['search']);
            $query->where(function (Builder $inner) use ($keyword): void {
                $inner->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%")
                    ->orWhereHas('businessInfo', function (Builder $business) use ($keyword): void {
                        $business->where('business_name', 'like', "%{$keyword}%");
                    });
            });
        }

        return $query->paginate(max(1, min($perPage, 50)));
    }

    public function findDiscoverableItem(int $catalogItemId): ?BusinessCatalogItem
    {
        return $this->discoveryBaseQuery()
            ->whereKey($catalogItemId)
            ->first();
    }

    /**
     * Public discoverable catalog query (active parent + active Premium).
     * Used by discovery feeds and sitemap generation — do not duplicate visibility rules.
     *
     * @return Builder<BusinessCatalogItem>
     */
    public function discoverableItemsQuery(): Builder
    {
        return $this->discoveryBaseQuery();
    }

    /**
     * Premium + active businesses, ranked for discovery.
     *
     * @return Builder<BusinessCatalogItem>
     */
    private function discoveryBaseQuery(): Builder
    {
        $viewerId = auth('api')->id();

        return BusinessCatalogItem::query()
            ->whereHas('businessInfo', function (Builder $business) use ($viewerId): void {
                $business->where('business_status', BusinessStatus::Active->value)
                    ->when($viewerId, fn (Builder $q) => $q->where('user_id', '!=', $viewerId))
                    ->whereHas('subscription', function (Builder $subscription): void {
                        $subscription->where('plan', SubscriptionPlan::Premium->value)
                            ->where('status', SubscriptionStatus::Active->value)
                            ->where(function (Builder $notExpired): void {
                                $notExpired->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    });
            })
            ->with([
                'businessInfo:id,business_name,category_id,location_id,user_id,business_status',
                'businessInfo.category:id,name',
                'businessInfo.location:id,city_name,state_name,lga_name',
                'businessInfo.user:id,uuid',
                'businessInfo.boost:id,business_info_id,is_active',
            ])
            ->orderByRaw('CASE WHEN image_paths IS NULL OR JSON_LENGTH(COALESCE(image_paths, JSON_ARRAY())) = 0 THEN 1 ELSE 0 END')
            ->orderByRaw('(SELECT CASE WHEN EXISTS (
                SELECT 1 FROM boosts
                WHERE boosts.business_info_id = business_catalog_items.business_info_id
                  AND boosts.is_active = 1
            ) THEN 0 ELSE 1 END)')
            ->latest('updated_at')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function assertCanManageCatalog(BusinessInfo $business): void
    {
        if (! $this->subscriptionService->hasActivePremium($business)) {
            throw ValidationException::withMessages([
                'catalog' => 'Upgrade to Premium to manage your product and service catalog.',
            ]);
        }
    }

    /**
     * Images are stored via MediaUploadService (media row + optimize job).
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $images
     */
    public function createItem(BusinessInfo $business, array $data, array $images = []): BusinessCatalogItem
    {
        $this->assertCanManageCatalog($business);

        $item = DB::transaction(function () use ($business, $data): BusinessCatalogItem {
            $currentCount = $business->catalogItems()->lockForUpdate()->count();
            if ($currentCount >= self::MAX_ITEMS_PER_BUSINESS) {
                throw ValidationException::withMessages([
                    'catalog' => 'You can add up to '.self::MAX_ITEMS_PER_BUSINESS.' catalog items.',
                ]);
            }

            $sortOrder = (int) ($data['sort_order'] ?? ($business->catalogItems()->max('sort_order') + 1));

            $pricing = $this->resolvePricingPayload($data, null);

            return $business->catalogItems()->create([
                'type' => $this->normalizeType($data['type'] ?? 'service'),
                'name' => trim((string) $data['name']),
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
                'price_kobo' => $pricing['price_kobo'],
                'original_price_kobo' => $pricing['original_price_kobo'],
                'price_label' => isset($data['price_label']) ? trim((string) $data['price_label']) : null,
                'price_from' => (bool) ($data['price_from'] ?? false),
                'discount_type' => $pricing['discount_type'],
                'discount_value' => $pricing['discount_value'],
                'has_discount' => $pricing['has_discount'],
                'image_paths' => null,
                'sort_order' => $sortOrder,
            ])->fresh();
        });

        if ($images === []) {
            return $item;
        }

        $paths = [];
        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $paths[] = $this->storeCatalogMedia($item, $image);
            }
        }

        if ($paths !== []) {
            $item->update(['image_paths' => $paths]);
        }

        return $item->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $images
     * @param  list<string>|null  $keepImagePaths
     */
    public function updateItem(
        BusinessInfo $business,
        BusinessCatalogItem $item,
        array $data,
        array $images = [],
        bool $removeImages = false,
        ?array $keepImagePaths = null,
    ): BusinessCatalogItem {
        $this->assertCanManageCatalog($business);
        $this->assertItemBelongsToBusiness($business, $item);

        return DB::transaction(function () use ($item, $data, $images, $removeImages, $keepImagePaths): BusinessCatalogItem {
            if (array_key_exists('type', $data)) {
                $item->type = $this->normalizeType($data['type']);
            }
            if (array_key_exists('name', $data)) {
                $item->name = trim((string) $data['name']);
            }
            if (array_key_exists('description', $data)) {
                $item->description = trim((string) $data['description']) ?: null;
            }
            if (array_key_exists('price_label', $data)) {
                $item->price_label = trim((string) $data['price_label']) ?: null;
            }
            if (
                array_key_exists('price_kobo', $data)
                || array_key_exists('price_from', $data)
                || array_key_exists('discount_type', $data)
                || array_key_exists('discount_value', $data)
            ) {
                $pricing = $this->resolvePricingPayload($data, $item);
                $item->price_kobo = $pricing['price_kobo'];
                $item->original_price_kobo = $pricing['original_price_kobo'];
                $item->discount_type = $pricing['discount_type'];
                $item->discount_value = $pricing['discount_value'];
                $item->has_discount = $pricing['has_discount'];
                if (array_key_exists('price_from', $data)) {
                    $item->price_from = (bool) $data['price_from'];
                }
            }
            if (array_key_exists('sort_order', $data)) {
                $item->sort_order = (int) $data['sort_order'];
            }

            $oldPaths = $item->normalizedImagePaths();
            $galleryTouched = $removeImages || $keepImagePaths !== null || $images !== [];

            if ($galleryTouched) {
                if ($removeImages) {
                    $keptPaths = [];
                } elseif ($keepImagePaths !== null) {
                    $keptPaths = [];
                    foreach ($keepImagePaths as $path) {
                        if (! is_string($path) || trim($path) === '') {
                            continue;
                        }
                        $normalized = trim($path);
                        if (in_array($normalized, $oldPaths, true)) {
                            $keptPaths[] = $normalized;
                        }
                    }
                } else {
                    $keptPaths = $oldPaths;
                }

                $newPaths = [];
                foreach ($images as $image) {
                    if ($image instanceof UploadedFile) {
                        $newPaths[] = $this->storeCatalogMedia($item, $image);
                    }
                }

                $finalPaths = array_values(array_unique(array_merge($keptPaths, $newPaths)));

                $removed = array_diff($oldPaths, $finalPaths);
                foreach ($removed as $path) {
                    Storage::disk('public')->delete($path);
                }

                $item->image_paths = $finalPaths === [] ? null : $finalPaths;
            }

            $item->save();

            return $item->fresh();
        });
    }

    private function storeCatalogMedia(BusinessCatalogItem $item, UploadedFile $file): string
    {
        $result = app(MediaUploadService::class)->store($file, $item, UploadableType::Product);

        return $result['media']->path;
    }

    public function deleteItem(BusinessInfo $business, BusinessCatalogItem $item): void
    {
        $this->assertCanManageCatalog($business);
        $this->assertItemBelongsToBusiness($business, $item);

        foreach ($item->normalizedImagePaths() as $path) {
            Storage::disk('public')->delete($path);
        }

        $item->delete();
    }

    public function resolveBusinessForUser(User $user, ?int $businessId = null): BusinessInfo
    {
        $business = app(BusinessInfoService::class)->findForUser($user, $businessId);

        if ($business === null) {
            throw ValidationException::withMessages([
                'business' => 'No business profile found.',
            ]);
        }

        return $business;
    }

    private function assertItemBelongsToBusiness(BusinessInfo $business, BusinessCatalogItem $item): void
    {
        if ((int) $item->business_info_id !== (int) $business->id) {
            throw ValidationException::withMessages([
                'catalog' => 'Catalog item not found for this business.',
            ]);
        }
    }

    private function normalizeType(mixed $type): string
    {
        $normalized = strtolower(trim((string) $type));

        return in_array($normalized, ['product', 'service'], true) ? $normalized : 'service';
    }

    /**
     * Vendor sends `price_kobo` as the list/base amount. We store sale in `price_kobo`
     * and list in `original_price_kobo` when a discount applies.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     price_kobo: int|null,
     *     original_price_kobo: int|null,
     *     discount_type: string|null,
     *     discount_value: int|null,
     *     has_discount: bool,
     * }
     */
    private function resolvePricingPayload(array $data, ?BusinessCatalogItem $existing): array
    {
        $listKobo = array_key_exists('price_kobo', $data)
            ? ($data['price_kobo'] !== null ? (int) $data['price_kobo'] : null)
            : (
                $existing
                    ? CatalogPricing::listPriceKobo(
                        $existing->price_kobo,
                        $existing->original_price_kobo,
                        (bool) $existing->has_discount,
                    )
                    : null
            );

        $priceFrom = array_key_exists('price_from', $data)
            ? (bool) $data['price_from']
            : (bool) ($existing?->price_from ?? false);

        $discountType = array_key_exists('discount_type', $data)
            ? $data['discount_type']
            : $existing?->discount_type;

        $discountValue = array_key_exists('discount_value', $data)
            ? $data['discount_value']
            : $existing?->discount_value;

        return CatalogPricing::resolveStoredPrices([
            'price_kobo' => $listKobo,
            'price_from' => $priceFrom,
            'discount_type' => is_string($discountType) || $discountType === null ? $discountType : null,
            'discount_value' => $discountValue !== null ? (int) $discountValue : null,
        ]);
    }
}
