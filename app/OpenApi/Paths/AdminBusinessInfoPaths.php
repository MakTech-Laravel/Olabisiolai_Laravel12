<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class AdminBusinessInfoPaths
{
    #[OA\Post(
    path: '/v1/admin/business-info',
    summary: 'Submit Business Info',
    tags: [
        'Admin',
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
                    property: 'search',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'verification_status',
                    enum: [
                        'none',
                        'pending',
                        'approved',
                    ],
                    example: 'none',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'business_status',
                    enum: [
                        'active',
                        'inactive',
                        'suspended',
                    ],
                    example: 'active',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'category_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'boost_status',
                    enum: [
                        'active',
                        'none',
                    ],
                    example: 'active',
                    type: 'string',
                    nullable: true,
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
                new OA\Property(
                    property: 'premium_source',
                    enum: [
                        'all',
                        'manual',
                    ],
                    example: 'all',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'per_page',
                    type: 'integer',
                    example: 10,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'page',
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
        description: 'Submit Business Info successfully',
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
    private function opPOSTAdminBusinessInfo_4c4047(): void {}

    #[OA\Post(
    path: '/v1/admin/business-info/view',
    summary: 'Submit View',
    tags: [
        'Admin',
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
                'business_info_id',
            ],
            properties: [
                new OA\Property(
                    property: 'business_info_id',
                    type: 'integer',
                    example: 1,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit View successfully',
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
    private function opPOSTAdminBusinessInfoView_f0b188(): void {}

    #[OA\Post(
    path: '/v1/admin/business-info/create',
    summary: 'Create Create',
    tags: [
        'Admin',
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
                'user_id',
                'location_id',
                'category_id',
                'business_name',
                'business_description',
                'services_offered',
                'phone',
            ],
            properties: [
                new OA\Property(
                    property: 'user_id',
                    type: 'integer',
                    example: 1,
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
                    property: 'business_name',
                    type: 'string',
                    example: 'John Doe',
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'business_description',
                    type: 'string',
                    example: 'Example description text.',
                    maxLength: 150,
                ),
                new OA\Property(
                    property: 'services_offered',
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
                    property: 'logo_path',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 500,
                ),
                new OA\Property(
                    property: 'cover_photo_paths',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
                new OA\Property(
                    property: 'verification_status',
                    enum: [
                        'none',
                        'pending',
                        'verified',
                        'approved',
                    ],
                    example: 'none',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'business_status',
                    enum: [
                        'active',
                        'inactive',
                        'suspended',
                    ],
                    example: 'active',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'is_flagged',
                    type: 'boolean',
                    example: true,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'verification_note',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 5000,
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
    private function opPOSTAdminBusinessInfoCreate_58f4df(): void {}

    #[OA\Post(
    path: '/v1/admin/business-info/update',
    summary: 'Update Update',
    tags: [
        'Admin',
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
                'business_info_id',
            ],
            properties: [
                new OA\Property(
                    property: 'business_info_id',
                    type: 'integer',
                    example: 1,
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
                    property: 'business_name',
                    type: 'string',
                    example: 'John Doe',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'business_description',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 150,
                ),
                new OA\Property(
                    property: 'services_offered',
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
                    nullable: true,
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
                    property: 'logo_path',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 500,
                ),
                new OA\Property(
                    property: 'cover_photo_paths',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
                new OA\Property(
                    property: 'verification_status',
                    enum: [
                        'none',
                        'pending',
                        'verified',
                        'approved',
                    ],
                    example: 'none',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'business_status',
                    enum: [
                        'active',
                        'inactive',
                        'suspended',
                    ],
                    example: 'active',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'is_flagged',
                    type: 'boolean',
                    example: true,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'verification_note',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 5000,
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
    private function opPOSTAdminBusinessInfoUpdate_6e5d50(): void {}

    #[OA\Post(
    path: '/v1/admin/business-info/delete',
    summary: 'Delete Delete',
    tags: [
        'Admin',
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
                'business_info_id',
            ],
            properties: [
                new OA\Property(
                    property: 'business_info_id',
                    type: 'integer',
                    example: 1,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Delete Delete successfully',
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
    private function opPOSTAdminBusinessInfoDelete_d5ff68(): void {}

    #[OA\Post(
    path: '/v1/admin/business-info/bulk-update',
    summary: 'Submit Bulk Update',
    tags: [
        'Admin',
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
                'business_ids',
            ],
            properties: [
                new OA\Property(
                    property: 'business_ids',
                    type: 'array',
                    items: new OA\Items(
                        type: 'integer',
                        example: 10,
                    ),
                ),
                new OA\Property(
                    property: 'verification_status',
                    enum: [
                        'none',
                        'pending',
                        'verified',
                        'approved',
                    ],
                    example: 'none',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'business_status',
                    enum: [
                        'active',
                        'inactive',
                        'suspended',
                    ],
                    example: 'active',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'is_flagged',
                    type: 'boolean',
                    example: true,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'verification_note',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 5000,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Bulk Update successfully',
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
    private function opPOSTAdminBusinessInfoBulkUpdate_585abd(): void {}

    #[OA\Post(
    path: '/v1/admin/business-info/status-change',
    summary: 'Submit Status Change',
    tags: [
        'Admin',
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
                'business_info_id',
                'status',
            ],
            properties: [
                new OA\Property(
                    property: 'business_info_id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'status',
                    enum: [
                        'active',
                        'inactive',
                        'suspended',
                    ],
                    example: 'active',
                    type: 'string',
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Status Change successfully',
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
    private function opPOSTAdminBusinessInfoStatusChange_b596f0(): void {}

    #[OA\Post(
    path: '/v1/admin/business-info/message',
    summary: 'Submit Message',
    tags: [
        'Admin',
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
                'business_info_id',
            ],
            properties: [
                new OA\Property(
                    property: 'business_info_id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 5000,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Message successfully',
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
    private function opPOSTAdminBusinessInfoMessage_76ef8f(): void {}

    #[OA\Post(
    path: '/v1/admin/business-info/statistics',
    summary: 'Submit Statistics',
    tags: [
        'Admin',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Statistics successfully',
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
    private function opPOSTAdminBusinessInfoStatistics_51678a(): void {}
}
