<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class UserReviewsPaths
{
    #[OA\Get(
    path: '/v1/user/reviews',
    summary: 'List Reviews',
    tags: [
        'Users',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
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
        response: 500,
        description: 'Unexpected server error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
    ],
)]
    private function opGETUserReviews_f90a7d(): void {}

    #[OA\Post(
    path: '/v1/user/reviews/{review}/report',
    summary: 'Create Report',
    tags: [
        'Users',
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
            required: [
                'reason',
            ],
            properties: [
                new OA\Property(
                    property: 'reason',
                    enum: [
                        'illegal_or_fraudulent',
                        'spam',
                        'wrong_price',
                        'wrong_category',
                        'seller_asked_for_prepayment',
                        'already_sold',
                        'other',
                    ],
                    example: 'illegal_or_fraudulent',
                    type: 'string',
                ),
                new OA\Property(
                    property: 'description',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 1000,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 201,
        description: 'Create Report successfully',
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
    private function opPOSTUserReviewsreviewReport_f66b0f(): void {}
}
