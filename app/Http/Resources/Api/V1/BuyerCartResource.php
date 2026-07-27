<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BuyerCart;
use App\Models\BuyerCartItem;
use App\Services\BuyerCatalogCartService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BuyerCart */
class BuyerCartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BuyerCart $cart */
        $cart = $this->resource;
        $cart->loadMissing(['items', 'businessInfo.user']);

        $service = app(BuyerCatalogCartService::class);
        $estimated = $service->estimatedTotalKobo($cart);
        $itemCount = (int) $cart->items->sum('quantity');
        $payload = $cart->status === BuyerCart::STATUS_SENT && is_array($cart->snapshot)
            ? $cart->snapshot
            : $service->buildCartMessagePayload($cart);

        return [
            'id' => $cart->id,
            'status' => $cart->status,
            'business_info_id' => (int) $cart->business_info_id,
            'business_name' => trim((string) ($cart->businessInfo?->business_name ?? 'Business')) ?: 'Business',
            'vendor_user_uuid' => $cart->businessInfo?->user?->uuid,
            'item_count' => $itemCount,
            'estimated_total_kobo' => $estimated,
            'estimated_total_display' => $service->estimatedTotalDisplay($cart),
            'sent_at' => $cart->sent_at?->toIso8601String(),
            'conversation_uuid' => $cart->relationLoaded('conversation')
                ? $cart->conversation?->uuid
                : null,
            'message_uuid' => $cart->relationLoaded('message')
                ? $cart->message?->uuid
                : null,
            'items' => $cart->items->map(static function (BuyerCartItem $line): array {
                $lineTotal = $line->lineTotalKobo();

                return [
                    'id' => $line->id,
                    'catalog_item_id' => (int) $line->catalog_item_id,
                    'name' => $line->name,
                    'quantity' => (int) $line->quantity,
                    'unit_price_kobo' => $line->unit_price_kobo,
                    'price_display' => $line->price_display,
                    'price_from' => (bool) $line->price_from,
                    'line_total_kobo' => $lineTotal,
                    'line_total_display' => $lineTotal === null
                        ? ''
                        : '₦'.number_format($lineTotal / 100, 0),
                    'image_url' => $line->image_url,
                ];
            })->values()->all(),
            'card' => [
                'cart_id' => $payload['cart_id'] ?? $cart->id,
                'item_count' => $payload['item_count'] ?? $itemCount,
                'estimated_total_display' => $payload['estimated_total_display'] ?? null,
                'business_name' => $payload['business_name'] ?? null,
                'thumbnail_url' => collect($payload['items'] ?? [])
                    ->first(static fn (array $row) => ! empty($row['image_url']))['image_url']
                    ?? $cart->items->first(static fn (BuyerCartItem $line) => (bool) $line->image_url)?->image_url,
            ],
        ];
    }
}
