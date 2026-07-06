<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class AdminReviewsPaths
{
    #[OA\Post(
    path: '/v1/admin/reviews',
    summary: 'List Reviews',
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
                    property: 'per_page',
                    type: 'string',
                    example: 'Example Text',
                ),
                new OA\Property(
                    property: 'business_id',
                    type: 'string',
                    example: 'Example Text',
                ),
                new OA\Property(
                    property: 'rating',
                    type: 'string',
                    example: 'Example Text',
                ),
                new OA\Property(
                    property: 'is_approved',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'is_flagged',
                    type: 'string',
                    example: 'Example Text',
                ),
                new OA\Property(
                    property: 'search',
                    type: 'string',
                    example: 'Example Text',
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'List Reviews successfully',
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
    private function opPOSTAdminReviews_70a0a3(): void {}

    #[OA\Post(
    path: '/v1/admin/reviews/{review}/view',
    summary: 'Get View',
    tags: [
        'Admin',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'review',
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
    private function opPOSTAdminReviewsreviewView_3fa4a5(): void {}

    #[OA\Post(
    path: '/v1/admin/reviews/{review}/update',
    summary: 'Update Update',
    tags: [
        'Admin',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'review',
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
            properties: [
                new OA\Property(
                    property: 'is_approved',
                    type: 'string',
                    example: 'Example Text',
                ),
                new OA\Property(
                    property: 'flag_reason',
                    type: 'string',
                    example: 'Example description text.',
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
    private function opPOSTAdminReviewsreviewUpdate_6730bc(): void {}

    #[OA\Post(
    path: '/v1/admin/reviews/{review}/delete',
    summary: 'Delete Delete',
    tags: [
        'Admin',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'review',
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
    private function opPOSTAdminReviewsreviewDelete_5ca414(): void {}

    #[OA\Post(
    path: '/v1/admin/reviews/bulk-approve',
    summary: 'Submit Bulk Approve',
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
                    property: 'review_ids',
                    type: 'string',
                    example: 'Example Text',
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Bulk Approve successfully',
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
    private function opPOSTAdminReviewsBulkApprove_3eec7b(): void {}

    #[OA\Post(
    path: '/v1/admin/reviews/bulk-flag',
    summary: 'Submit Bulk Flag',
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
                    property: 'review_ids',
                    type: 'string',
                    example: 'Example Text',
                ),
                new OA\Property(
                    property: 'flag_reason',
                    type: 'string',
                    example: 'Example description text.',
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Bulk Flag successfully',
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
    private function opPOSTAdminReviewsBulkFlag_1e8b28(): void {}

    #[OA\Post(
    path: '/v1/admin/reviews/statistics',
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
    private function opPOSTAdminReviewsStatistics_db6542(): void {}
}
