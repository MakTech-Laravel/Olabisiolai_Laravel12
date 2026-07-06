<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class VendorCatalogPaths
{
    #[OA\Get(
    path: '/v1/vendor/catalog',
    summary: 'List Catalog',
    tags: [
        'Vendors',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'List Catalog successfully',
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
    private function opGETVendorCatalog_837800(): void {}

    #[OA\Post(
    path: '/v1/vendor/catalog',
    summary: 'Create Catalog',
    tags: [
        'Vendors',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: [
                    'type',
                    'name',
                ],
                properties: [
                new OA\Property(
                    property: 'business_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'type',
                    enum: [
                        'product',
                        'service',
                    ],
                    example: 'product',
                    type: 'string',
                ),
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    example: 'John Doe',
                    maxLength: 120,
                ),
                new OA\Property(
                    property: 'description',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 500,
                ),
                new OA\Property(
                    property: 'price_kobo',
                    type: 'integer',
                    example: 10,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'price_label',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 64,
                ),
                new OA\Property(
                    property: 'price_from',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'image',
                    type: 'string',
                    format: 'binary',
                ),
            ],
            ),
        ),
    ),
    responses: [
        new OA\Response(
        response: 201,
        description: 'Create Catalog successfully',
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
    private function opPOSTVendorCatalog_f64615(): void {}

    #[OA\Post(
    path: '/v1/vendor/catalog/{catalogItem}',
    summary: 'Update Catalog',
    tags: [
        'Vendors',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'catalogItem',
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
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                new OA\Property(
                    property: 'business_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'type',
                    enum: [
                        'product',
                        'service',
                    ],
                    example: 'product',
                    type: 'string',
                ),
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    example: 'John Doe',
                    maxLength: 120,
                ),
                new OA\Property(
                    property: 'description',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 500,
                ),
                new OA\Property(
                    property: 'price_kobo',
                    type: 'integer',
                    example: 10,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'price_label',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 64,
                ),
                new OA\Property(
                    property: 'price_from',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'remove_image',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'image',
                    type: 'string',
                    format: 'binary',
                ),
            ],
            ),
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Update Catalog successfully',
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
    private function opPOSTVendorCatalogcatalogItem_2bafec(): void {}

    #[OA\Delete(
    path: '/v1/vendor/catalog/{catalogItem}',
    summary: 'Delete Catalog',
    tags: [
        'Vendors',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'catalogItem',
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
        description: 'Delete Catalog successfully',
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
    private function opDELETEVendorCatalogcatalogItem_3964dc(): void {}
}
