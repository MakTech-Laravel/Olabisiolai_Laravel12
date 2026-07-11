<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class VendorBoostPaths
{
    #[OA\Get(
    path: '/v1/vendor/boost/catalog',
    summary: 'Get Catalog',
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
        description: 'Get Catalog successfully',
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
    private function opGETVendorBoostCatalog_d5ef57(): void {}

    #[OA\Post(
    path: '/v1/vendor/boost/request',
    summary: 'Submit Request',
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
                'duration_days',
                'budget_amount',
            ],
            properties: [
                new OA\Property(
                    property: 'duration_days',
                    enum: [
                        1,
                        3,
                        7,
                        14,
                        30,
                    ],
                    example: 1,
                    type: 'integer',
                ),
                new OA\Property(
                    property: 'budget_amount',
                    type: 'number',
                    example: 5000,
                ),
                new OA\Property(
                    property: 'location_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'renew_type',
                    enum: [
                        'extend',
                        'boost_again',
                    ],
                    example: 'extend',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'source_campaign_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'gateway',
                    enum: [
                        'flutterwave',
                        'paystack',
                        'wallet',
                    ],
                    example: 'flutterwave',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'use_wallet',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'apply_wallet',
                    type: 'boolean',
                    example: true,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Request successfully',
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
    private function opPOSTVendorBoostRequest_ccbd8a(): void {}

    #[OA\Post(
    path: '/v1/vendor/boost/payment/init',
    summary: 'Submit Init',
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
                'duration_days',
                'budget_amount',
            ],
            properties: [
                new OA\Property(
                    property: 'duration_days',
                    enum: [
                        1,
                        3,
                        7,
                        14,
                        30,
                    ],
                    example: 1,
                    type: 'integer',
                ),
                new OA\Property(
                    property: 'budget_amount',
                    type: 'number',
                    example: 5000,
                ),
                new OA\Property(
                    property: 'location_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'renew_type',
                    enum: [
                        'extend',
                        'boost_again',
                    ],
                    example: 'extend',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'source_campaign_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'gateway',
                    enum: [
                        'flutterwave',
                        'paystack',
                        'wallet',
                    ],
                    example: 'flutterwave',
                    type: 'string',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'use_wallet',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'apply_wallet',
                    type: 'boolean',
                    example: true,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Init successfully',
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
    private function opPOSTVendorBoostPaymentInit_1ebd36(): void {}

    #[OA\Post(
    path: '/v1/vendor/boost/payment/resume',
    summary: 'Submit Resume',
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
                'request_id',
            ],
            properties: [
                new OA\Property(
                    property: 'request_id',
                    type: 'integer',
                    example: 1,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Resume successfully',
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
    private function opPOSTVendorBoostPaymentResume_adb9f1(): void {}

    #[OA\Post(
    path: '/v1/vendor/boost/payment/confirm',
    summary: 'Submit Confirm',
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
                'payment_id',
                'gateway_transaction_id',
                'gateway',
            ],
            properties: [
                new OA\Property(
                    property: 'payment_id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'gateway_transaction_id',
                    type: 'string',
                    example: 'Example Text',
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'gateway',
                    enum: [
                        'flutterwave',
                        'paystack',
                        'wallet',
                    ],
                    example: 'flutterwave',
                    type: 'string',
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Confirm successfully',
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
    private function opPOSTVendorBoostPaymentConfirm_a0cdeb(): void {}
}
