<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class AdminSubscriptionPlansPaths
{
    #[OA\Post(
    path: '/v1/admin/subscription-plans',
    summary: 'List Subscription Plans',
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
        description: 'List Subscription Plans successfully',
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
    private function opPOSTAdminSubscriptionPlans_44dfe1(): void {}

    #[OA\Post(
    path: '/v1/admin/subscription-plans/store',
    summary: 'Create Store',
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
                'package_key',
                'title',
                'billing_period',
                'amount',
            ],
            properties: [
                new OA\Property(
                    property: 'package_key',
                    type: 'string',
                    example: 'Example Text',
                    maxLength: 50,
                ),
                new OA\Property(
                    property: 'title',
                    type: 'string',
                    example: 'Example Title',
                    maxLength: 120,
                ),
                new OA\Property(
                    property: 'billing_period',
                    enum: [
                        'monthly',
                        'quarterly',
                        'yearly',
                        'lifetime',
                    ],
                    example: 'monthly',
                    type: 'string',
                ),
                new OA\Property(
                    property: 'amount',
                    type: 'integer',
                    example: 10,
                ),
                new OA\Property(
                    property: 'original_price',
                    type: 'integer',
                    example: 10,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'promotional_text',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'promotion_starts_at',
                    type: 'string',
                    format: 'date',
                    example: '2026-01-15',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'promotion_ends_at',
                    type: 'string',
                    format: 'date',
                    example: '2026-01-15',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'description',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 2000,
                ),
                new OA\Property(
                    property: 'perks',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
                new OA\Property(
                    property: 'is_active',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'is_recommended',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'trial_eligible',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'trial_duration_days',
                    type: 'integer',
                    example: 10,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'sort_order',
                    type: 'integer',
                    example: 10,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 201,
        description: 'Create Store successfully',
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
    private function opPOSTAdminSubscriptionPlansStore_248f8e(): void {}

    #[OA\Post(
    path: '/v1/admin/subscription-plans/update',
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
                'id',
            ],
            properties: [
                new OA\Property(
                    property: 'id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'package_key',
                    type: 'string',
                    example: 'Example Text',
                    maxLength: 50,
                ),
                new OA\Property(
                    property: 'title',
                    type: 'string',
                    example: 'Example Title',
                    maxLength: 120,
                ),
                new OA\Property(
                    property: 'billing_period',
                    enum: [
                        'monthly',
                        'quarterly',
                        'yearly',
                        'lifetime',
                    ],
                    example: 'monthly',
                    type: 'string',
                ),
                new OA\Property(
                    property: 'amount',
                    type: 'integer',
                    example: 10,
                ),
                new OA\Property(
                    property: 'original_price',
                    type: 'integer',
                    example: 10,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'promotional_text',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'promotion_starts_at',
                    type: 'string',
                    format: 'date',
                    example: '2026-01-15',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'promotion_ends_at',
                    type: 'string',
                    format: 'date',
                    example: '2026-01-15',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'description',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 2000,
                ),
                new OA\Property(
                    property: 'perks',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
                new OA\Property(
                    property: 'is_active',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'is_recommended',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'trial_eligible',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'trial_duration_days',
                    type: 'integer',
                    example: 10,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'sort_order',
                    type: 'integer',
                    example: 10,
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
    private function opPOSTAdminSubscriptionPlansUpdate_c0ecd6(): void {}

    #[OA\Post(
    path: '/v1/admin/subscription-plans/delete',
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
                'id',
            ],
            properties: [
                new OA\Property(
                    property: 'id',
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
    private function opPOSTAdminSubscriptionPlansDelete_9b4e35(): void {}

    #[OA\Post(
    path: '/v1/admin/subscription-plans/toggle-active',
    summary: 'Submit Toggle Active',
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
                'id',
                'is_active',
            ],
            properties: [
                new OA\Property(
                    property: 'id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'is_active',
                    type: 'boolean',
                    example: true,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Toggle Active successfully',
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
    private function opPOSTAdminSubscriptionPlansToggleActive_ac6a9e(): void {}

    #[OA\Post(
    path: '/v1/admin/subscription-plans/set-recommended',
    summary: 'Submit Set Recommended',
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
                'id',
            ],
            properties: [
                new OA\Property(
                    property: 'id',
                    type: 'integer',
                    example: 1,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Set Recommended successfully',
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
    private function opPOSTAdminSubscriptionPlansSetRecommended_3033a5(): void {}

    #[OA\Post(
    path: '/v1/admin/subscription-plans/reorder',
    summary: 'Submit Reorder',
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
                'ordered_ids',
            ],
            properties: [
                new OA\Property(
                    property: 'ordered_ids',
                    type: 'array',
                    items: new OA\Items(
                        type: 'integer',
                        example: 10,
                    ),
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Reorder successfully',
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
    private function opPOSTAdminSubscriptionPlansReorder_f71d1e(): void {}
}
