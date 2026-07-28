<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class PublicCartPaths
{
    #[OA\Get(
    path: '/v1/cart',
    summary: 'List Cart',
    tags: [
        'Public',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'List Cart successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 500,
        description: 'Unexpected server error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
    ],
)]
    private function opGETCart_991c37(): void {}

    #[OA\Post(
    path: '/v1/cart/items',
    summary: 'Submit Items',
    tags: [
        'Public',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                'catalog_item_id',
            ],
            properties: [
                new OA\Property(
                    property: 'catalog_item_id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'quantity',
                    type: 'integer',
                    example: 10,
                    nullable: true,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Items successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 500,
        description: 'Unexpected server error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
    ],
)]
    private function opPOSTCartItems_5a6c0b(): void {}

    #[OA\Patch(
    path: '/v1/cart/items/{id}',
    summary: 'Update Items',
    tags: [
        'Public',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(
        type: 'integer',
    ),
        example: 1,
    ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                'quantity',
            ],
            properties: [
                new OA\Property(
                    property: 'quantity',
                    type: 'integer',
                    example: 10,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Update Items successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 404,
        description: 'Not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 500,
        description: 'Unexpected server error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
    ],
)]
    private function opPATCHCartItemsid_f40211(): void {}

    #[OA\Delete(
    path: '/v1/cart/items/{id}',
    summary: 'Delete Items',
    tags: [
        'Public',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(
        type: 'integer',
    ),
        example: 1,
    ),
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Delete Items successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 404,
        description: 'Not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 500,
        description: 'Unexpected server error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
    ],
)]
    private function opDELETECartItemsid_045bcf(): void {}

    #[OA\Post(
    path: '/v1/cart/send',
    summary: 'Submit Send',
    tags: [
        'Public',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'cart_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'business_info_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Send successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 500,
        description: 'Unexpected server error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
    ],
)]
    private function opPOSTCartSend_b60d48(): void {}
}
