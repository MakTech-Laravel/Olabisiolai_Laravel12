<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessInfo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessCatalogService
{
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
     * Small curated homepage strip: premium-vendor catalog items only.
     *
     * @return Collection<int, BusinessCatalogItem>
     */
    public function curatedPremiumHomeItems(int $limit = 6): Collection
    {
        return $this->discoveryBaseQuery()
            ->limit(max(1, min($limit, 12)))
            ->get();
    }

    /**
     * Full Catalog-tab discovery feed (premium vendors, with optional filters).
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

    /**
     * Premium + active businesses, ranked for discovery.
     *
     * @return Builder<BusinessCatalogItem>
     */
    private function discoveryBaseQuery(): Builder
    {
        return BusinessCatalogItem::query()
            ->whereHas('businessInfo', function (Builder $business): void {
                $business->where('business_status', BusinessStatus::Active->value)
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
            ->orderByRaw('CASE WHEN image_path IS NULL OR image_path = \'\' THEN 1 ELSE 0 END')
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
     * @param  array<string, mixed>  $data
     */
    public function createItem(BusinessInfo $business, array $data, ?UploadedFile $image = null): BusinessCatalogItem
    {
        $this->assertCanManageCatalog($business);

        return DB::transaction(function () use ($business, $data, $image): BusinessCatalogItem {
            $sortOrder = (int) ($data['sort_order'] ?? ($business->catalogItems()->max('sort_order') + 1));

            $item = $business->catalogItems()->create([
                'type' => $this->normalizeType($data['type'] ?? 'service'),
                'name' => trim((string) $data['name']),
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
                'price_kobo' => isset($data['price_kobo']) ? (int) $data['price_kobo'] : null,
                'price_label' => isset($data['price_label']) ? trim((string) $data['price_label']) : null,
                'price_from' => (bool) ($data['price_from'] ?? false),
                'sort_order' => $sortOrder,
            ]);

            if ($image !== null) {
                $item->image_path = $this->storeImage($business, $image);
                $item->save();
            }

            return $item->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateItem(
        BusinessInfo $business,
        BusinessCatalogItem $item,
        array $data,
        ?UploadedFile $image = null,
        bool $removeImage = false,
    ): BusinessCatalogItem {
        $this->assertCanManageCatalog($business);
        $this->assertItemBelongsToBusiness($business, $item);

        return DB::transaction(function () use ($business, $item, $data, $image, $removeImage): BusinessCatalogItem {
            if (array_key_exists('type', $data)) {
                $item->type = $this->normalizeType($data['type']);
            }
            if (array_key_exists('name', $data)) {
                $item->name = trim((string) $data['name']);
            }
            if (array_key_exists('description', $data)) {
                $item->description = trim((string) $data['description']) ?: null;
            }
            if (array_key_exists('price_kobo', $data)) {
                $item->price_kobo = $data['price_kobo'] !== null ? (int) $data['price_kobo'] : null;
            }
            if (array_key_exists('price_label', $data)) {
                $item->price_label = trim((string) $data['price_label']) ?: null;
            }
            if (array_key_exists('price_from', $data)) {
                $item->price_from = (bool) $data['price_from'];
            }
            if (array_key_exists('sort_order', $data)) {
                $item->sort_order = (int) $data['sort_order'];
            }

            if ($removeImage) {
                $item->image_path = null;
            } elseif ($image !== null) {
                $item->image_path = $this->storeImage($business, $image);
            }

            $item->save();

            return $item->fresh();
        });
    }

    public function deleteItem(BusinessInfo $business, BusinessCatalogItem $item): void
    {
        $this->assertCanManageCatalog($business);
        $this->assertItemBelongsToBusiness($business, $item);
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

    private function storeImage(BusinessInfo $business, UploadedFile $image): string
    {
        return $image->store("businesses/{$business->id}/catalog", 'public');
    }
}
