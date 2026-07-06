<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class VendorSubscriptionPaths
{
    #[OA\Post(
    path: '/v1/vendor/subscription/payment/reconcile',
    summary: 'Submit Reconcile',
    tags: [
        'Vendors',
        'Billing',
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
                'paystack_reference',
            ],
            properties: [
                new OA\Property(
                    property: 'paystack_reference',
                    type: 'string',
                    example: 'Example Text',
                    maxLength: 255,
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
        description: 'Submit Reconcile successfully',
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
    private function opPOSTVendorSubscriptionPaymentReconcile_c4e0ae(): void {}
}
