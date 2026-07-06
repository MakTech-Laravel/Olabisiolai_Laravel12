<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class AdminSubscriptionsPaths
{
    #[OA\Post(
    path: '/v1/admin/subscriptions/grant-premium',
    summary: 'Submit Grant Premium',
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
                'business_id',
                'reason',
            ],
            properties: [
                new OA\Property(
                    property: 'business_id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'reason',
                    type: 'string',
                    example: 'Example description text.',
                    maxLength: 500,
                ),
                new OA\Property(
                    property: 'duration_days',
                    type: 'integer',
                    example: 10,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'paystack_reference',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Grant Premium successfully',
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
    private function opPOSTAdminSubscriptionsGrantPremium_37a7ea(): void {}
}
