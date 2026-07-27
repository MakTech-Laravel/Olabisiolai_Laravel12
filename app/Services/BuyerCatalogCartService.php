<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Messaging\ConversationDTO;
use App\DTOs\Messaging\MessageDTO;
use App\Enums\ConversationType;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessInfo;
use App\Models\BuyerCart;
use App\Models\BuyerCartItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BuyerCatalogCartService
{
    public const MAX_QTY = 99;

    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly ConversationService $conversationService,
        private readonly MessageService $messageService,
    ) {}

    /**
     * @return Collection<int, BuyerCart>
     */
    public function listOpenCarts(User $user): Collection
    {
        $carts = BuyerCart::query()
            ->with(['items.catalogItem', 'businessInfo.user'])
            ->where('user_id', $user->id)
            ->where('status', BuyerCart::STATUS_OPEN)
            ->orderByDesc('updated_at')
            ->get();

        foreach ($carts as $cart) {
            $this->healLinePrices($cart);
        }

        return $carts;
    }

    public function getOpenCart(User $user, int $businessInfoId, bool $createIfMissing = false): ?BuyerCart
    {
        $cart = BuyerCart::query()
            ->with(['items.catalogItem', 'businessInfo.user'])
            ->where('user_id', $user->id)
            ->where('business_info_id', $businessInfoId)
            ->where('status', BuyerCart::STATUS_OPEN)
            ->first();

        if ($cart) {
            $this->healLinePrices($cart);

            return $cart;
        }

        if (! $createIfMissing) {
            return null;
        }

        return $this->createOpenCart($user, $businessInfoId);
    }

    public function createOpenCart(User $user, int $businessInfoId): BuyerCart
    {
        $business = $this->assertCartableBusiness($user, $businessInfoId);

        return BuyerCart::query()->create([
            'user_id' => $user->id,
            'business_info_id' => $business->id,
            'status' => BuyerCart::STATUS_OPEN,
        ])->load(['items', 'businessInfo.user']);
    }

    public function addItem(User $user, int $catalogItemId, int $quantity = 1): BuyerCart
    {
        $quantity = $this->clampQty($quantity);
        $item = $this->findDiscoverableCatalogItem($catalogItemId);
        $this->assertCartableBusiness($user, (int) $item->business_info_id);

        return DB::transaction(function () use ($user, $item, $quantity): BuyerCart {
            $cart = BuyerCart::query()
                ->where('user_id', $user->id)
                ->where('business_info_id', $item->business_info_id)
                ->where('status', BuyerCart::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if (! $cart) {
                $cart = BuyerCart::query()->create([
                    'user_id' => $user->id,
                    'business_info_id' => $item->business_info_id,
                    'status' => BuyerCart::STATUS_OPEN,
                ]);
            }

            /** @var BuyerCartItem|null $line */
            $line = $cart->items()->where('catalog_item_id', $item->id)->lockForUpdate()->first();
            $snapshot = $this->snapshotFromCatalogItem($item);

            if ($line) {
                $line->fill([
                    ...$snapshot,
                    'quantity' => $this->clampQty($line->quantity + $quantity),
                ]);
                $line->save();
            } else {
                $cart->items()->create([
                    ...$snapshot,
                    'catalog_item_id' => $item->id,
                    'quantity' => $quantity,
                ]);
            }

            $cart->touch();

            return $cart->fresh(['items', 'businessInfo.user']);
        });
    }

    public function setItemQuantity(User $user, int $cartItemId, int $quantity): BuyerCart
    {
        return DB::transaction(function () use ($user, $cartItemId, $quantity): BuyerCart {
            $line = $this->findOwnedCartItem($user, $cartItemId);
            $cart = $line->cart;

            if (! $cart || ! $cart->isOpen()) {
                throw ValidationException::withMessages([
                    'cart' => 'This cart can no longer be modified.',
                ]);
            }

            if ($quantity < 1) {
                $line->delete();
            } else {
                $line->quantity = $this->clampQty($quantity);
                $line->save();
            }

            $cart->touch();

            return $cart->fresh(['items', 'businessInfo.user']);
        });
    }

    public function removeItem(User $user, int $cartItemId): BuyerCart
    {
        return $this->setItemQuantity($user, $cartItemId, 0);
    }

    /**
     * @return array{cart: BuyerCart, message: \App\Models\Message, conversation: \App\Models\Conversation}
     */
    public function sendCart(User $user, int $cartId): array
    {
        return DB::transaction(function () use ($user, $cartId): array {
            /** @var BuyerCart|null $cart */
            $cart = BuyerCart::query()
                ->with(['items', 'businessInfo.user'])
                ->whereKey($cartId)
                ->where('user_id', $user->id)
                ->where('status', BuyerCart::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if (! $cart) {
                throw ValidationException::withMessages([
                    'cart_id' => 'Open cart not found.',
                ]);
            }

            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Your cart is empty.',
                ]);
            }

            $business = $cart->businessInfo;
            if (! $business || ! $business->user_id) {
                throw ValidationException::withMessages([
                    'business' => 'This business cannot receive cart messages yet.',
                ]);
            }

            if ((int) $business->user_id === (int) $user->id) {
                throw ValidationException::withMessages([
                    'business' => 'You cannot send a cart to your own business.',
                ]);
            }

            $this->assertBusinessIsPremium($business);

            $conversation = $this->conversationService->createConversation(
                new ConversationDTO(
                    type: ConversationType::Direct,
                    name: null,
                    participantUserIds: [(int) $business->user_id],
                    businessInfoId: (int) $business->id,
                ),
                $user,
            );

            $payload = $this->buildCartMessagePayload($cart);
            $body = '[GIDIRA_CART]'.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'[/GIDIRA_CART]';

            $message = $this->messageService->sendMessage(
                new MessageDTO(
                    conversationUuid: $conversation->uuid,
                    body: $body,
                    parentId: null,
                    attachmentIds: [],
                ),
                $user,
            );

            $cart->fill([
                'status' => BuyerCart::STATUS_SENT,
                'sent_at' => now(),
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'snapshot' => $payload,
            ]);
            $cart->save();

            return [
                'cart' => $cart->fresh(['items', 'businessInfo.user']),
                'message' => $message,
                'conversation' => $conversation,
            ];
        });
    }

    public function getSentCartForViewer(User $user, int $cartId): BuyerCart
    {
        /** @var BuyerCart|null $cart */
        $cart = BuyerCart::query()
            ->with(['items', 'businessInfo.user', 'conversation.participantRows'])
            ->whereKey($cartId)
            ->where('status', BuyerCart::STATUS_SENT)
            ->first();

        if (! $cart) {
            throw ValidationException::withMessages([
                'cart' => 'Sent cart not found.',
            ]);
        }

        $isOwner = (int) $cart->user_id === (int) $user->id;
        $isVendor = (int) ($cart->businessInfo?->user_id) === (int) $user->id;
        $isParticipant = $cart->conversation
            ? $cart->conversation->participantRows->contains(fn ($row) => (int) $row->user_id === (int) $user->id)
            : false;

        if (! $isOwner && ! $isVendor && ! $isParticipant) {
            throw ValidationException::withMessages([
                'cart' => 'You do not have access to this sent cart.',
            ]);
        }

        return $cart;
    }

    private function assertCartableBusiness(User $user, int $businessInfoId): BusinessInfo
    {
        /** @var BusinessInfo|null $business */
        $business = BusinessInfo::query()->with('user')->find($businessInfoId);
        if (! $business) {
            throw ValidationException::withMessages([
                'business_info_id' => 'Business not found.',
            ]);
        }

        if ((int) $business->user_id === (int) $user->id) {
            throw ValidationException::withMessages([
                'business' => 'You cannot add items from your own business to a cart.',
            ]);
        }

        $this->assertBusinessIsPremium($business);

        return $business;
    }

    private function assertBusinessIsPremium(BusinessInfo $business): void
    {
        if (! $this->subscriptionService->hasActivePremium($business)) {
            throw ValidationException::withMessages([
                'catalog' => 'Cart is only available for premium business catalogs.',
            ]);
        }
    }

    private function findDiscoverableCatalogItem(int $catalogItemId): BusinessCatalogItem
    {
        /** @var BusinessCatalogItem|null $item */
        $item = BusinessCatalogItem::query()
            ->with(['businessInfo.user'])
            ->whereKey($catalogItemId)
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'catalog_item_id' => 'Catalog item not found.',
            ]);
        }

        return $item;
    }

    private function findOwnedCartItem(User $user, int $cartItemId): BuyerCartItem
    {
        /** @var BuyerCartItem|null $line */
        $line = BuyerCartItem::query()
            ->with('cart')
            ->whereKey($cartItemId)
            ->first();

        if (! $line || (int) $line->cart?->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'id' => 'Cart item not found.',
            ]);
        }

        return $line;
    }

    /**
     * Re-sync open-cart line pricing when the catalog item changes between
     * exact amounts and non-exact ("from" / free-text label) pricing.
     */
    private function healLinePrices(BuyerCart $cart): void
    {
        $cart->loadMissing('items.catalogItem');

        foreach ($cart->items as $line) {
            if (! $line->catalogItem) {
                continue;
            }

            $snapshot = $this->snapshotFromCatalogItem($line->catalogItem);
            $sameUnit = $line->unit_price_kobo === null
                ? $snapshot['unit_price_kobo'] === null
                : $snapshot['unit_price_kobo'] !== null
                    && (int) $line->unit_price_kobo === (int) $snapshot['unit_price_kobo'];

            if (
                $sameUnit
                && $line->price_display === $snapshot['price_display']
                && (bool) $line->price_from === (bool) $snapshot['price_from']
            ) {
                continue;
            }

            $line->fill([
                'unit_price_kobo' => $snapshot['unit_price_kobo'],
                'price_display' => $snapshot['price_display'],
                'price_from' => $snapshot['price_from'],
            ]);
            $line->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFromCatalogItem(BusinessCatalogItem $item): array
    {
        $paths = $item->normalizedImagePaths();
        $urls = array_values(array_filter(
            array_map(static fn (string $path) => public_media_url($path, null), $paths),
        ));

        $priceFrom = (bool) $item->price_from;
        // Exact cart math only when there is a numeric price and it is not a "from" estimate.
        $unitPriceKobo = ($item->price_kobo !== null && ! $priceFrom)
            ? (int) $item->price_kobo
            : null;

        return [
            'name' => trim((string) $item->name),
            'unit_price_kobo' => $unitPriceKobo,
            'price_display' => $this->formatCatalogPriceDisplay($item),
            'price_from' => $priceFrom,
            'image_url' => $urls[0] ?? null,
        ];
    }

    /** Display label for cart lines; exact totals only when unit_price_kobo is set. */
    private function formatCatalogPriceDisplay(BusinessCatalogItem $item): string
    {
        if ($item->price_kobo !== null) {
            $naira = '₦'.number_format(((int) $item->price_kobo) / 100, 0);

            return $item->price_from ? 'from '.$naira : $naira;
        }

        $label = trim((string) ($item->price_label ?? ''));
        if ($label !== '') {
            return $item->price_from && ! str_starts_with(strtolower($label), 'from ')
                ? 'from '.$label
                : $label;
        }

        return 'Price on request';
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCartMessagePayload(BuyerCart $cart): array
    {
        $cart->loadMissing(['items', 'businessInfo']);
        $itemCount = (int) $cart->items->sum('quantity');
        $estimated = $this->estimatedTotalKobo($cart);

        return [
            'v' => 1,
            'cart_id' => $cart->id,
            'business_info_id' => (int) $cart->business_info_id,
            'business_name' => trim((string) ($cart->businessInfo?->business_name ?? 'Business')) ?: 'Business',
            'sent_at' => now()->toIso8601String(),
            'estimated_total_kobo' => $estimated,
            'estimated_total_display' => $estimated === null
                ? 'Business will provide total price'
                : '₦'.number_format($estimated / 100, 0),
            'item_count' => $itemCount,
            'items' => $cart->items->map(static function (BuyerCartItem $line): array {
                $lineTotal = $line->lineTotalKobo();

                return [
                    'id' => (int) $line->catalog_item_id,
                    'cart_item_id' => (int) $line->id,
                    'name' => $line->name,
                    'qty' => (int) $line->quantity,
                    'price_display' => $line->price_display,
                    'price_from' => (bool) $line->price_from,
                    'line_total_kobo' => $lineTotal,
                    // From / estimate lines omit an amount — business confirms the total.
                    'line_total_display' => $lineTotal === null
                        ? ''
                        : '₦'.number_format($lineTotal / 100, 0),
                    'image_url' => $line->image_url,
                ];
            })->values()->all(),
        ];
    }

    public function estimatedTotalKobo(BuyerCart $cart): ?int
    {
        $total = 0;
        foreach ($cart->items as $line) {
            $lineTotal = $line->lineTotalKobo();
            if ($lineTotal === null) {
                return null;
            }
            $total += $lineTotal;
        }

        return $total;
    }

    /** Human-readable cart total label (exact estimate vs business-provided). */
    public function estimatedTotalDisplay(BuyerCart $cart): string
    {
        $estimated = $this->estimatedTotalKobo($cart);

        return $estimated === null
            ? 'Business will provide total price'
            : '₦'.number_format($estimated / 100, 0);
    }

    private function clampQty(int $quantity): int
    {
        if ($quantity < 1) {
            return 1;
        }

        return min(self::MAX_QTY, $quantity);
    }
}
