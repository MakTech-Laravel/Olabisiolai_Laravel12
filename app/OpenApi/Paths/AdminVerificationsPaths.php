<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class AdminVerificationsPaths
{
    #[OA\Post(
    path: '/v1/admin/verifications',
    summary: 'List Verifications',
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
                    property: 'verification_status',
                    enum: [
                        'none',
                        'pending',
                        'approved',
                        'flagged',
                        'queue',
                        'all',
                        'needs_reverification',
                    ],
                    example: 'none',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'search',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'per_page',
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
        description: 'List Verifications successfully',
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
    private function opPOSTAdminVerifications_ecabd4(): void {}

    #[OA\Post(
    path: '/v1/admin/verifications/view',
    summary: 'Get View',
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
        description: 'Get View successfully',
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
    private function opPOSTAdminVerificationsView_e81c06(): void {}

    #[OA\Post(
    path: '/v1/admin/verifications/approve',
    summary: 'Submit Approve',
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
                    property: 'note',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 2000,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Approve successfully',
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
    private function opPOSTAdminVerificationsApprove_75208b(): void {}

    #[OA\Post(
    path: '/v1/admin/verifications/flag',
    summary: 'Submit Flag',
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
                'reason',
            ],
            properties: [
                new OA\Property(
                    property: 'business_info_id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'reason',
                    type: 'string',
                    example: 'Example description text.',
                    minLength: 10,
                    maxLength: 2000,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Flag successfully',
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
    private function opPOSTAdminVerificationsFlag_2aad43(): void {}

    #[OA\Post(
    path: '/v1/admin/verifications/delete',
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
                new OA\Property(
                    property: 'reason',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 2000,
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
    private function opPOSTAdminVerificationsDelete_30bc38(): void {}

    #[OA\Post(
    path: '/v1/admin/verifications/grant-reverification',
    summary: 'Submit Grant Reverification',
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
                'reason',
            ],
            properties: [
                new OA\Property(
                    property: 'business_info_id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'reason',
                    type: 'string',
                    example: 'Example description text.',
                    minLength: 3,
                    maxLength: 2000,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Grant Reverification successfully',
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
    private function opPOSTAdminVerificationsGrantReverification_8c6766(): void {}

    #[OA\Post(
    path: '/v1/admin/verifications/reapprove',
    summary: 'Submit Reapprove',
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
                    property: 'note',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 2000,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Reapprove successfully',
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
    private function opPOSTAdminVerificationsReapprove_7686f5(): void {}

    #[OA\Post(
    path: '/v1/admin/verifications/note',
    summary: 'Submit Note',
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
                'note',
                'note_type',
                'is_visible_to_vendor',
            ],
            properties: [
                new OA\Property(
                    property: 'business_info_id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'note',
                    type: 'string',
                    example: 'Example description text.',
                    minLength: 3,
                    maxLength: 5000,
                ),
                new OA\Property(
                    property: 'note_type',
                    enum: [
                        'internal',
                        'vendor_communication',
                        'admin_decision',
                    ],
                    example: 'internal',
                    type: 'string',
                ),
                new OA\Property(
                    property: 'is_visible_to_vendor',
                    type: 'boolean',
                    example: true,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Note successfully',
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
    private function opPOSTAdminVerificationsNote_399686(): void {}

    #[OA\Post(
    path: '/v1/admin/verifications/documents/review',
    summary: 'Submit Review',
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
                'document_id',
                'action',
            ],
            properties: [
                new OA\Property(
                    property: 'document_id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'action',
                    enum: [
                        'approve',
                        'reject',
                    ],
                    example: 'approve',
                    type: 'string',
                ),
                new OA\Property(
                    property: 'reason',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 2000,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Review successfully',
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
    private function opPOSTAdminVerificationsDocumentsReview_3994ea(): void {}
}
