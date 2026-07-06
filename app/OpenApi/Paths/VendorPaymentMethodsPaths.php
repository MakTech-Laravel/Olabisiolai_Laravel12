<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class VendorPaymentMethodsPaths
{
    #[OA\Get(
    path: '/v1/vendor/payment-methods',
    summary: 'List Payment Methods',
    tags: [
        'Vendors',
        'Billing',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'List Payment Methods successfully',
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
    private function opGETVendorPaymentMethods_dd2520(): void {}

    #[OA\Post(
    path: '/v1/vendor/payment-methods',
    summary: 'Create Payment Methods',
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
                'cardholder_name',
                'email',
                'phone',
            ],
            properties: [
                new OA\Property(
                    property: 'label',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 80,
                ),
                new OA\Property(
                    property: 'cardholder_name',
                    type: 'string',
                    example: 'John Doe',
                    maxLength: 160,
                ),
                new OA\Property(
                    property: 'email',
                    type: 'string',
                    format: 'email',
                    example: 'john.doe@example.com',
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'phone',
                    type: 'string',
                    example: '+2348012345678',
                    maxLength: 32,
                ),
                new OA\Property(
                    property: 'last_four',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'card_brand',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 48,
                ),
                new OA\Property(
                    property: 'exp_month',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 2,
                ),
                new OA\Property(
                    property: 'exp_year',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 4,
                ),
                new OA\Property(
                    property: 'billing_line1',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'billing_city',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 120,
                ),
                new OA\Property(
                    property: 'billing_state',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 120,
                ),
                new OA\Property(
                    property: 'billing_country',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 120,
                ),
                new OA\Property(
                    property: 'is_default',
                    type: 'boolean',
                    example: true,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 201,
        description: 'Create Payment Methods successfully',
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
    private function opPOSTVendorPaymentMethods_896c17(): void {}

    #[OA\Patch(
    path: '/v1/vendor/payment-methods/{paymentMethod}/default',
    summary: 'Update Default',
    tags: [
        'Vendors',
        'Billing',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'paymentMethod',
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
        description: 'Update Default successfully',
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
    private function opPATCHVendorPaymentMethodspaymentMethodDefault_0263af(): void {}

    #[OA\Delete(
    path: '/v1/vendor/payment-methods/{paymentMethod}',
    summary: 'Delete Payment Methods',
    tags: [
        'Vendors',
        'Billing',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'paymentMethod',
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
        description: 'Delete Payment Methods successfully',
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
    private function opDELETEVendorPaymentMethodspaymentMethod_0e20c0(): void {}
}
