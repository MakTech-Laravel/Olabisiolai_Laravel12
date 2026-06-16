<?php

namespace App\Services;

use App\Models\BusinessCatalogItem;
use App\Models\BusinessInfo;
use App\Models\User;
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
