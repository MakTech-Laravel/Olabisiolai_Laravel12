<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class VendorSettingsPaths
{
    #[OA\Get(
    path: '/v1/vendor/settings',
    summary: 'Get Settings',
    tags: [
        'Vendors',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Get Settings successfully',
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
    private function opGETVendorSettings_bbff41(): void {}

    #[OA\Patch(
    path: '/v1/vendor/settings',
    summary: 'Update Settings',
    tags: [
        'Vendors',
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
                    property: 'first_name',
                    type: 'string',
                    example: 'John',
                    nullable: true,
                    maxLength: 120,
                ),
                new OA\Property(
                    property: 'last_name',
                    type: 'string',
                    example: 'Doe',
                    nullable: true,
                    maxLength: 120,
                ),
                new OA\Property(
                    property: 'business_name',
                    type: 'string',
                    example: 'John Doe',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'phone',
                    type: 'string',
                    example: '+2348012345678',
                    nullable: true,
                    maxLength: 30,
                ),
                new OA\Property(
                    property: 'logo',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'settings',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Update Settings successfully',
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
    private function opPATCHVendorSettings_7e1def(): void {}
}
