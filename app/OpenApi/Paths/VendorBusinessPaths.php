<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class VendorBusinessPaths
{
    #[OA\Get(
    path: '/v1/vendor/business/form-options',
    summary: 'Get Form Options',
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
        description: 'Get Form Options successfully',
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
    private function opGETVendorBusinessFormOptions_2c97ac(): void {}

    #[OA\Post(
    path: '/v1/vendor/business/create',
    summary: 'Create Create',
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
        content: new OA\JsonContent(
            required: [
                'location_id',
                'category_id',
                'business_name',
                'business_description',
                'services',
                'phone',
                'logo',
                'cover_photos',
            ],
            properties: [
                new OA\Property(
                    property: 'business_hours',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'day',
                                enum: [
                                    'monday',
                                    'tuesday',
                                    'wednesday',
                                    'thursday',
                                    'friday',
                                    'saturday',
                                    'sunday',
                                ],
                                example: 'monday',
                                type: 'string',
                            ),
                            new OA\Property(
                                property: 'is_closed',
                                type: 'boolean',
                                example: true,
                            ),
                            new OA\Property(
                                property: 'is_24_hours',
                                description: 'When true, the day is treated as open 24 hours (00:00 - 23:59) regardless of opens_at/closes_at.',
                                type: 'boolean',
                                example: false,
                            ),
                            new OA\Property(
                                property: 'opens_at',
                                type: 'string',
                                example: 'Example Text',
                                nullable: true,
                            ),
                            new OA\Property(
                                property: 'closes_at',
                                type: 'string',
                                example: 'Example Text',
                                nullable: true,
                            ),
                        ],
                    ),
                ),
                new OA\Property(
                    property: 'location_id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'category_id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'subcategory',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'business_name',
                    type: 'string',
                    example: 'John Doe',
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'full_address',
                    type: 'string',
                    example: '12 Adeola Odeku Street, Victoria Island',
                    nullable: true,
                    maxLength: 500,
                ),
                new OA\Property(
                    property: 'street_address',
                    type: 'string',
                    example: '12 Adeola Odeku Street, Victoria Island',
                    nullable: true,
                    maxLength: 500,
                ),
                new OA\Property(
                    property: 'business_description',
                    type: 'string',
                    example: 'Example description text.',
                    maxLength: 150,
                ),
                new OA\Property(
                    property: 'services',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
                new OA\Property(
                    property: 'phone',
                    type: 'string',
                    example: '+2348012345678',
                ),
                new OA\Property(
                    property: 'whatsapp',
                    type: 'string',
                    example: '+2348012345678',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'website',
                    type: 'string',
                    format: 'uri',
                    example: 'https://example.com',
                    nullable: true,
                    maxLength: 2048,
                ),
                new OA\Property(
                    property: 'social_accounts',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'platform',
                                enum: [
                                    'instagram',
                                    'facebook',
                                    'x',
                                    'linkedin',
                                    'tiktok',
                                    'youtube',
                                    'pinterest',
                                    'threads',
                                    'snapchat',
                                ],
                                example: 'instagram',
                                type: 'string',
                            ),
                            new OA\Property(
                                property: 'url',
                                type: 'string',
                                example: 'Example Text',
                                maxLength: 2048,
                            ),
                        ],
                    ),
                ),
                new OA\Property(
                    property: 'logo',
                    type: 'string',
                    example: 'Example Text',
                ),
                new OA\Property(
                    property: 'cover_photos',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
                new OA\Property(
                    property: 'subscription_plan',
                    enum: [
                        'free',
                        'premium',
                    ],
                    example: 'free',
                    type: 'string',
                    nullable: true,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 201,
        description: 'Create Create successfully',
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
    private function opPOSTVendorBusinessCreate_9d6159(): void {}

    #[OA\Get(
    path: '/v1/vendor/business/show',
    summary: 'Get Show',
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
        description: 'Get Show successfully',
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
    private function opGETVendorBusinessShow_ec7fca(): void {}

    #[OA\Put(
    path: '/v1/vendor/business/update',
    summary: 'Update Update',
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
        content: new OA\JsonContent(
            required: [
                'business_name',
                'business_description',
                'services',
                'phone',
            ],
            properties: [
                new OA\Property(
                    property: 'business_hours',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'day',
                                enum: [
                                    'monday',
                                    'tuesday',
                                    'wednesday',
                                    'thursday',
                                    'friday',
                                    'saturday',
                                    'sunday',
                                ],
                                example: 'monday',
                                type: 'string',
                            ),
                            new OA\Property(
                                property: 'is_closed',
                                type: 'boolean',
                                example: true,
                            ),
                            new OA\Property(
                                property: 'is_24_hours',
                                description: 'When true, the day is treated as open 24 hours (00:00 - 23:59) regardless of opens_at/closes_at.',
                                type: 'boolean',
                                example: false,
                            ),
                            new OA\Property(
                                property: 'opens_at',
                                type: 'string',
                                example: 'Example Text',
                                nullable: true,
                            ),
                            new OA\Property(
                                property: 'closes_at',
                                type: 'string',
                                example: 'Example Text',
                                nullable: true,
                            ),
                        ],
                    ),
                ),
                new OA\Property(
                    property: 'location_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'category_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'subcategory',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'business_name',
                    type: 'string',
                    example: 'John Doe',
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'full_address',
                    type: 'string',
                    example: '12 Adeola Odeku Street, Victoria Island',
                    nullable: true,
                    maxLength: 500,
                ),
                new OA\Property(
                    property: 'street_address',
                    type: 'string',
                    example: '12 Adeola Odeku Street, Victoria Island',
                    nullable: true,
                    maxLength: 500,
                ),
                new OA\Property(
                    property: 'latitude',
                    type: 'number',
                    example: 99.99,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'longitude',
                    type: 'number',
                    example: 99.99,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'google_place_id',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'business_description',
                    type: 'string',
                    example: 'Example description text.',
                    maxLength: 150,
                ),
                new OA\Property(
                    property: 'services',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
                new OA\Property(
                    property: 'phone',
                    type: 'string',
                    example: '+2348012345678',
                    maxLength: 30,
                ),
                new OA\Property(
                    property: 'whatsapp',
                    type: 'string',
                    example: '+2348012345678',
                    nullable: true,
                    maxLength: 30,
                ),
                new OA\Property(
                    property: 'website',
                    type: 'string',
                    format: 'uri',
                    example: 'https://example.com',
                    nullable: true,
                    maxLength: 2048,
                ),
                new OA\Property(
                    property: 'social_accounts',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'platform',
                                enum: [
                                    'instagram',
                                    'facebook',
                                    'x',
                                    'linkedin',
                                    'tiktok',
                                    'youtube',
                                    'pinterest',
                                    'threads',
                                    'snapchat',
                                ],
                                example: 'instagram',
                                type: 'string',
                            ),
                            new OA\Property(
                                property: 'url',
                                type: 'string',
                                example: 'Example Text',
                                maxLength: 2048,
                            ),
                        ],
                    ),
                ),
                new OA\Property(
                    property: 'logo',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'keep_cover_paths',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
                new OA\Property(
                    property: 'cover_photos',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Update Update successfully',
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
    private function opPUTVendorBusinessUpdate_c18398(): void {}

    #[OA\Post(
    path: '/v1/vendor/business/update',
    summary: 'Update Update',
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
        content: new OA\JsonContent(
            required: [
                'business_name',
                'business_description',
                'services',
                'phone',
            ],
            properties: [
                new OA\Property(
                    property: 'business_hours',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'day',
                                enum: [
                                    'monday',
                                    'tuesday',
                                    'wednesday',
                                    'thursday',
                                    'friday',
                                    'saturday',
                                    'sunday',
                                ],
                                example: 'monday',
                                type: 'string',
                            ),
                            new OA\Property(
                                property: 'is_closed',
                                type: 'boolean',
                                example: true,
                            ),
                            new OA\Property(
                                property: 'is_24_hours',
                                description: 'When true, the day is treated as open 24 hours (00:00 - 23:59) regardless of opens_at/closes_at.',
                                type: 'boolean',
                                example: false,
                            ),
                            new OA\Property(
                                property: 'opens_at',
                                type: 'string',
                                example: 'Example Text',
                                nullable: true,
                            ),
                            new OA\Property(
                                property: 'closes_at',
                                type: 'string',
                                example: 'Example Text',
                                nullable: true,
                            ),
                        ],
                    ),
                ),
                new OA\Property(
                    property: 'location_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'category_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'subcategory',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'business_name',
                    type: 'string',
                    example: 'John Doe',
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'full_address',
                    type: 'string',
                    example: '12 Adeola Odeku Street, Victoria Island',
                    nullable: true,
                    maxLength: 500,
                ),
                new OA\Property(
                    property: 'street_address',
                    type: 'string',
                    example: '12 Adeola Odeku Street, Victoria Island',
                    nullable: true,
                    maxLength: 500,
                ),
                new OA\Property(
                    property: 'latitude',
                    type: 'number',
                    example: 99.99,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'longitude',
                    type: 'number',
                    example: 99.99,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'google_place_id',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'business_description',
                    type: 'string',
                    example: 'Example description text.',
                    maxLength: 150,
                ),
                new OA\Property(
                    property: 'services',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
                new OA\Property(
                    property: 'phone',
                    type: 'string',
                    example: '+2348012345678',
                    maxLength: 30,
                ),
                new OA\Property(
                    property: 'whatsapp',
                    type: 'string',
                    example: '+2348012345678',
                    nullable: true,
                    maxLength: 30,
                ),
                new OA\Property(
                    property: 'website',
                    type: 'string',
                    format: 'uri',
                    example: 'https://example.com',
                    nullable: true,
                    maxLength: 2048,
                ),
                new OA\Property(
                    property: 'social_accounts',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'platform',
                                enum: [
                                    'instagram',
                                    'facebook',
                                    'x',
                                    'linkedin',
                                    'tiktok',
                                    'youtube',
                                    'pinterest',
                                    'threads',
                                    'snapchat',
                                ],
                                example: 'instagram',
                                type: 'string',
                            ),
                            new OA\Property(
                                property: 'url',
                                type: 'string',
                                example: 'Example Text',
                                maxLength: 2048,
                            ),
                        ],
                    ),
                ),
                new OA\Property(
                    property: 'logo',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'keep_cover_paths',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
                new OA\Property(
                    property: 'cover_photos',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Update Update successfully',
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
    private function opPOSTVendorBusinessUpdate_69c1c6(): void {}

    #[OA\Post(
    path: '/v1/vendor/business/boost-status',
    summary: 'Submit Boost Status',
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
        content: new OA\JsonContent(
            required: [
                'is_active',
            ],
            properties: [
                new OA\Property(
                    property: 'is_active',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'business_id',
                    type: 'integer',
                    example: 1,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Boost Status successfully',
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
    private function opPOSTVendorBusinessBoostStatus_323a65(): void {}
}
