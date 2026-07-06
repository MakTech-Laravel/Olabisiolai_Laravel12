<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class AuthPaths
{
    #[OA\Post(
    path: '/v1/auth/admin/two-factor/resend-otp',
    summary: 'Submit Resend Otp',
    tags: [
        'Auth',
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                'two_factor_token',
            ],
            properties: [
                new OA\Property(
                    property: 'two_factor_token',
                    type: 'string',
                    example: 'Example Text',
                    minLength: 32,
                    maxLength: 128,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Resend Otp successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
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
    private function opPOSTAuthAdminTwoFactorResendOtp_ad98ff(): void {}

    #[OA\Get(
    path: '/v1/auth/social/providers',
    summary: 'Get Providers',
    tags: [
        'Auth',
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Get Providers successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
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
    private function opGETAuthSocialProviders_01291f(): void {}

    #[OA\Get(
    path: '/v1/auth/social/{provider}/redirect',
    summary: 'Get Redirect',
    tags: [
        'Auth',
    ],
    parameters: [
        new OA\Parameter(
        name: 'provider',
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
        description: 'Get Redirect successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
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
    private function opGETAuthSocialproviderRedirect_3f5083(): void {}

    #[OA\Post(
    path: '/v1/auth/social/{provider}/login',
    summary: 'Submit Login',
    tags: [
        'Auth',
    ],
    parameters: [
        new OA\Parameter(
        name: 'provider',
        in: 'path',
        required: true,
        schema: new OA\Schema(
        type: 'integer',
    ),
        example: 1,
    ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                'role',
            ],
            properties: [
                new OA\Property(
                    property: 'role',
                    enum: [
                        'user',
                        'vendor',
                    ],
                    example: 'user',
                    type: 'string',
                ),
                new OA\Property(
                    property: 'access_token',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'id_token',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'code',
                    type: 'string',
                    example: 'GID1A2B3C',
                    nullable: true,
                ),
                new OA\Property(
                    property: 'redirect_uri',
                    type: 'string',
                    format: 'uri',
                    example: 'https://example.com',
                    nullable: true,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Login successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
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
    private function opPOSTAuthSocialproviderLogin_2b29ce(): void {}

    #[OA\Get(
    path: '/v1/auth/social/{provider}/callback',
    summary: 'Get Callback',
    tags: [
        'Auth',
    ],
    parameters: [
        new OA\Parameter(
        name: 'provider',
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
        description: 'Get Callback successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
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
    private function opGETAuthSocialproviderCallback_eb9b7f(): void {}
}
