<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class BuyerCatalogCartPaths
{
    #[OA\Get(
        path: '/v1/cart',
        summary: 'Get open buyer cart(s)',
        description: 'Returns all open carts for the authenticated user, or a single cart when business_info_id is provided.',
        tags: ['Buyer Cart'],
        security: [['passport' => []]],
        parameters: [
            new OA\Parameter(
                name: 'business_info_id',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 12),
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cart(s) retrieved', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    private function getCart(): void {}

    #[OA\Post(
        path: '/v1/cart/items',
        summary: 'Add catalog item to cart',
        tags: ['Buyer Cart'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['catalog_item_id'],
                properties: [
                    new OA\Property(property: 'catalog_item_id', type: 'integer', example: 1),
                    new OA\Property(property: 'quantity', type: 'integer', example: 1, minimum: 1, maximum: 99),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Item added', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    private function postCartItem(): void {}

    #[OA\Patch(
        path: '/v1/cart/items/{id}',
        summary: 'Update cart line quantity (0 removes)',
        tags: ['Buyer Cart'],
        security: [['passport' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['quantity'],
                properties: [
                    new OA\Property(property: 'quantity', type: 'integer', example: 2, minimum: 0, maximum: 99),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    private function patchCartItem(): void {}

    #[OA\Delete(
        path: '/v1/cart/items/{id}',
        summary: 'Remove cart line',
        tags: ['Buyer Cart'],
        security: [['passport' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Removed', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
        ],
    )]
    private function deleteCartItem(): void {}

    #[OA\Post(
        path: '/v1/cart/send',
        summary: 'Send open cart to business as a chat cart card',
        description: 'Creates/reuses a direct conversation, posts a GIDIRA_CART message, freezes a cart snapshot, and returns conversation_uuid + message.',
        tags: ['Buyer Cart'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'cart_id', type: 'integer', example: 5, nullable: true),
                    new OA\Property(property: 'business_info_id', type: 'integer', example: 12, nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Cart sent', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    private function postCartSend(): void {}

    #[OA\Get(
        path: '/v1/messages/carts/{cartMessageId}',
        summary: 'Fetch a previously sent cart snapshot',
        tags: ['Buyer Cart'],
        security: [['passport' => []]],
        parameters: [
            new OA\Parameter(
                name: 'cartMessageId',
                in: 'path',
                required: true,
                description: 'Sent buyer cart id (also present as cart_id in the GIDIRA_CART message payload)',
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sent cart', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 422, description: 'Not found / no access', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    private function getSentCart(): void {}

}
