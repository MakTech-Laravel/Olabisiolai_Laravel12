<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class UserSettingsPaths
{
    #[OA\Post(
    path: '/v1/user/settings',
    summary: 'Update Settings',
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
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                new OA\Property(
                    property: 'first_name',
                    type: 'string',
                    example: 'John',
                    maxLength: 120,
                ),
                new OA\Property(
                    property: 'last_name',
                    type: 'string',
                    example: 'Doe',
                    maxLength: 120,
                ),
                new OA\Property(
                    property: 'phone',
                    type: 'string',
                    example: '+2348012345678',
                    nullable: true,
                    maxLength: 20,
                ),
                new OA\Property(
                    property: 'wants_marketing_emails',
                    type: 'boolean',
                    example: true,
                ),
                new OA\Property(
                    property: 'location',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'image',
                    description: 'Image file upload',
                    type: 'string',
                    format: 'binary',
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
    private function opPOSTUserSettings_9af2b0(): void {}
}
