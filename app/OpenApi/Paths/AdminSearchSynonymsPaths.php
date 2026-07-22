<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class AdminSearchSynonymsPaths
{
    #[OA\Post(
    path: '/v1/admin/search-synonyms',
    summary: 'Submit Search Synonyms',
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
            properties: [
                new OA\Property(
                    property: 'search',
                    type: 'string',
                    example: 'Example Text',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'per_page',
                    type: 'integer',
                    example: 10,
                    nullable: true,
                ),
                new OA\Property(
                    property: 'page',
                    type: 'integer',
                    example: 10,
                    nullable: true,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Search Synonyms successfully',
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
    private function opPOSTAdminSearchSynonyms_ac6828(): void {}

    #[OA\Post(
    path: '/v1/admin/search-synonyms/create',
    summary: 'Submit Create',
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
                'term',
                'synonyms',
            ],
            properties: [
                new OA\Property(
                    property: 'term',
                    type: 'string',
                    example: 'Example Text',
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'synonyms',
                    type: 'string',
                    example: 'Example Text',
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Create successfully',
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
    private function opPOSTAdminSearchSynonymsCreate_fc9a6c(): void {}

    #[OA\Post(
    path: '/v1/admin/search-synonyms/view',
    summary: 'Submit View',
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
                'id',
            ],
            properties: [
                new OA\Property(
                    property: 'id',
                    type: 'integer',
                    example: 1,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit View successfully',
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
    private function opPOSTAdminSearchSynonymsView_0ac023(): void {}

    #[OA\Post(
    path: '/v1/admin/search-synonyms/update',
    summary: 'Submit Update',
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
                'id',
                'term',
                'synonyms',
            ],
            properties: [
                new OA\Property(
                    property: 'id',
                    type: 'integer',
                    example: 1,
                ),
                new OA\Property(
                    property: 'term',
                    type: 'string',
                    example: 'Example Text',
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'synonyms',
                    type: 'string',
                    example: 'Example Text',
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Update successfully',
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
    private function opPOSTAdminSearchSynonymsUpdate_14f5d1(): void {}

    #[OA\Post(
    path: '/v1/admin/search-synonyms/delete',
    summary: 'Submit Delete',
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
                'id',
            ],
            properties: [
                new OA\Property(
                    property: 'id',
                    type: 'integer',
                    example: 1,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Delete successfully',
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
    private function opPOSTAdminSearchSynonymsDelete_4dd230(): void {}
}
