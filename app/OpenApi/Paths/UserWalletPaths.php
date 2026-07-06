<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class UserWalletPaths
{
    #[OA\Get(
    path: '/v1/user/wallet',
    summary: 'Get Wallet',
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
        description: 'Get Wallet successfully',
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
    private function opGETUserWallet_0bd9fa(): void {}

    #[OA\Post(
    path: '/v1/user/wallet/top-up',
    summary: 'Submit Top Up',
    tags: [
        'Users',
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
                'amount',
            ],
            properties: [
                new OA\Property(
                    property: 'amount',
                    type: 'number',
                    example: 5000,
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
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Top Up successfully',
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
    private function opPOSTUserWalletTopUp_4e2529(): void {}

    #[OA\Post(
    path: '/v1/user/wallet/top-up/confirm',
    summary: 'Submit Confirm',
    tags: [
        'Users',
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
    private function opPOSTUserWalletTopUpConfirm_1cf5fe(): void {}
}
