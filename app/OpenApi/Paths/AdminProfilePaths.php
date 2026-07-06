<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class AdminProfilePaths
{
    #[OA\Get(
    path: '/v1/admin/profile',
    summary: 'Get Profile',
    tags: [
        'Admin',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Get Profile successfully',
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
    private function opGETAdminProfile_3b05ef(): void {}

    #[OA\Put(
    path: '/v1/admin/profile',
    summary: 'Update Profile',
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
                'first_name',
                'last_name',
                'email',
            ],
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
                    nullable: true,
                    maxLength: 30,
                ),
                new OA\Property(
                    property: 'current_password',
                    type: 'string',
                    example: 'Example Text',
                ),
                new OA\Property(
                    property: 'password',
                    type: 'string',
                    format: 'password',
                    example: 'Password@123',
                    nullable: true,
                    minLength: 8,
                ),
                new OA\Property(
                    property: 'password_confirmation',
                    type: 'string',
                    format: 'password',
                    example: 'Password@123',
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Update Profile successfully',
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
    private function opPUTAdminProfile_6a8c75(): void {}
}
