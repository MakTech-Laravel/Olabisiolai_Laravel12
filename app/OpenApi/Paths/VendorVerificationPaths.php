<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class VendorVerificationPaths
{
    #[OA\Get(
    path: '/v1/vendor/verification/packages',
    summary: 'Get Packages',
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
        description: 'Get Packages successfully',
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
    private function opGETVendorVerificationPackages_b1ba00(): void {}

    #[OA\Post(
    path: '/v1/vendor/verification/payment/init',
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
                'package_id',
            ],
            properties: [
                new OA\Property(
                    property: 'package_id',
                    enum: [
                        'individual',
                        'business',
                        'ltd',
                    ],
                    example: 'individual',
                    type: 'string',
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
    private function opPOSTVendorVerificationPaymentInit_ae58af(): void {}

    #[OA\Post(
    path: '/v1/vendor/verification/payment/confirm',
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
    private function opPOSTVendorVerificationPaymentConfirm_fc555c(): void {}

    #[OA\Post(
    path: '/v1/vendor/verification/apply',
    summary: 'Submit Apply',
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
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: [
                    'payment_id',
                    'documents',
                ],
                properties: [
                new OA\Property(
                    property: 'payment_id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'documents',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'document_type',
                                enum: [
                                    'payment_receipt',
                                    'bank_transfer',
                                    'business_registration',
                                    'cac_document',
                                    'identity_proof',
                                    'address_proof',
                                    'other',
                                ],
                                example: 'payment_receipt',
                                type: 'string',
                            ),
                            new OA\Property(
                                property: 'title',
                                type: 'string',
                                example: 'Example Title',
                                maxLength: 255,
                            ),
                            new OA\Property(
                                property: 'description',
                                type: 'string',
                                example: 'Example description text.',
                                nullable: true,
                                maxLength: 1000,
                            ),
                            new OA\Property(
                                property: 'document',
                                type: 'string',
                                format: 'binary',
                            ),
                        ],
                    ),
                ),
            ],
            ),
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Apply successfully',
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
    private function opPOSTVendorVerificationApply_52e7e3(): void {}

    #[OA\Post(
    path: '/v1/vendor/verification/documents/upload',
    summary: 'Submit Upload',
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
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: [
                    'document_type',
                    'title',
                    'document',
                ],
                properties: [
                new OA\Property(
                    property: 'document_type',
                    enum: [
                        'payment_receipt',
                        'bank_transfer',
                        'business_registration',
                        'cac_document',
                        'identity_proof',
                        'address_proof',
                        'other',
                    ],
                    example: 'payment_receipt',
                    type: 'string',
                ),
                new OA\Property(
                    property: 'title',
                    type: 'string',
                    example: 'Example Title',
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'description',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 1000,
                ),
                new OA\Property(
                    property: 'parent_document_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'document',
                    type: 'string',
                    format: 'binary',
                ),
            ],
            ),
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Upload successfully',
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
    private function opPOSTVendorVerificationDocumentsUpload_a2676b(): void {}

    #[OA\Get(
    path: '/v1/vendor/verification/status',
    summary: 'Get Status',
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
        description: 'Get Status successfully',
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
    private function opGETVendorVerificationStatus_2af267(): void {}
}
