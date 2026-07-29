<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BuyerCartResource;
use App\Http\Resources\MessageResource;
use App\Models\User;
use App\Services\BuyerCatalogCartService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BuyerCatalogCartController extends Controller
{
    public function __construct(
        private readonly BuyerCatalogCartService $carts,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        try {
            $validated = $request->validate([
                'business_info_id' => ['nullable', 'integer', 'min:1'],
            ]);

            if (! empty($validated['business_info_id'])) {
                $cart = $this->carts->getOpenCart($user, (int) $validated['business_info_id']);

                return sendResponse(true, 'Cart retrieved successfully.', [
                    'cart' => $cart ? (new BuyerCartResource($cart))->resolve() : null,
                ]);
            }

            $carts = $this->carts->listOpenCarts($user);

            return sendResponse(true, 'Carts retrieved successfully.', [
                'carts' => BuyerCartResource::collection($carts)->resolve(),
                'count' => $carts->count(),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                collect($exception->errors())->flatten()->first() ?: $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable) {
            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function storeItem(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        try {
            $validated = $request->validate([
                'catalog_item_id' => ['required', 'integer', 'min:1'],
                'quantity' => ['nullable', 'integer', 'min:1', 'max:'.BuyerCatalogCartService::MAX_QTY],
            ]);

            $cart = $this->carts->addItem(
                $user,
                (int) $validated['catalog_item_id'],
                (int) ($validated['quantity'] ?? 1),
            );

            return sendResponse(true, 'Item added to cart.', [
                'cart' => (new BuyerCartResource($cart))->resolve(),
            ], Response::HTTP_CREATED);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                collect($exception->errors())->flatten()->first() ?: $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable) {
            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateItem(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        try {
            $validated = $request->validate([
                'quantity' => ['required', 'integer', 'min:0', 'max:'.BuyerCatalogCartService::MAX_QTY],
            ]);

            $cart = $this->carts->setItemQuantity($user, $id, (int) $validated['quantity']);

            return sendResponse(true, 'Cart item updated.', [
                'cart' => (new BuyerCartResource($cart))->resolve(),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                collect($exception->errors())->flatten()->first() ?: $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable) {
            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroyItem(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        try {
            $cart = $this->carts->removeItem($user, $id);

            return sendResponse(true, 'Cart item removed.', [
                'cart' => (new BuyerCartResource($cart))->resolve(),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                collect($exception->errors())->flatten()->first() ?: $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable) {
            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function send(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        try {
            $validated = $request->validate([
                'cart_id' => ['nullable', 'integer', 'min:1'],
                'business_info_id' => ['nullable', 'integer', 'min:1'],
            ]);

            $cartId = isset($validated['cart_id']) ? (int) $validated['cart_id'] : null;
            if (! $cartId && ! empty($validated['business_info_id'])) {
                $open = $this->carts->getOpenCart($user, (int) $validated['business_info_id']);
                $cartId = $open?->id;
            }

            if (! $cartId) {
                return sendResponse(false, 'cart_id or business_info_id is required.', [
                    'errors' => ['cart_id' => ['Provide cart_id or business_info_id.']],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $result = $this->carts->sendCart($user, $cartId);

            return sendResponse(true, 'Cart sent to business.', [
                'cart' => (new BuyerCartResource($result['cart']->load(['conversation', 'message'])))->resolve(),
                'conversation_uuid' => $result['conversation']->uuid,
                'message' => (new MessageResource($result['message']))->resolve(),
            ], Response::HTTP_CREATED);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                collect($exception->errors())->flatten()->first() ?: $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $exception) {
            $message = $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'Something went wrong. Please try again.';

            return sendResponse(false, $message, null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function showSent(Request $request, int $cartMessageId): Response
    {
        /** @var User $user */
        $user = $request->user('api');

        try {
            $cart = $this->carts->getSentCartForViewer($user, $cartMessageId);

            return sendResponse(true, 'Sent cart retrieved successfully.', [
                'cart' => (new BuyerCartResource($cart->load(['conversation', 'message'])))->resolve(),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                collect($exception->errors())->flatten()->first() ?: $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable) {
            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
