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
                        example: 'ExampleText',
                        nullable: true,
                        maxLength: 255,
                    ),
                    new OA\Property(
                        property: 'payment_handling',
                        type: 'string',
                        enum: ['waived', 'recorded'],
                        nullable: true,
                    ),
                    new OA\Property(
                        property: 'payment_method',
                        type: 'string',
                        enum: ['bank_transfer', 'cash', 'other'],
                        nullable: true,
                    ),
                    new OA\Property(
                        property: 'payment_reference',
                        type: 'string',
                        nullable: true,
                        maxLength: 255,
                    ),
                    new OA\Property(
                        property: 'amount',
                        type: 'number',
                        nullable: true,
                    ),
                    new OA\Property(
                        property: 'package_id',
                        type: 'string',
                        example: 'premium_yearly',
                        nullable: true,
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

    #[OA\Post(
        path: '/v1/admin/subscriptions/expiration-tracker',
        summary: 'Premium expiration tracker',
        tags: [
            'Admin',
        ],
        security: [
            [
                'passport' => [],
            ],
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'page', type: 'integer', example: 1, nullable: true),
                    new OA\Property(property: 'per_page', type: 'integer', example: 15, nullable: true),
                    new OA\Property(property: 'search', type: 'string', nullable: true),
                    new OA\Property(
                        property: 'urgency',
                        type: 'string',
                        enum: ['all', 'active', 'expiring_soon', 'expired'],
                        nullable: true,
                    ),
                    new OA\Property(property: 'days_ahead', type: 'integer', example: 14, nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Premium expiration tracker loaded',
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
    private function opPOSTAdminSubscriptionsExpirationTracker(): void {}
}
