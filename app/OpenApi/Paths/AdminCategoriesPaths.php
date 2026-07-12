<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class AdminCategoriesPaths
{
    #[OA\Post(
        path: '/v1/admin/categories',
        summary: 'Submit Categories',
        tags: ['Admin'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'search', type: 'string', example: 'Example text', nullable: true, maxLength: 255),
                    new OA\Property(property: 'per_page', type: 'integer', example: 10, nullable: true),
                    new OA\Property(property: 'page', type: 'integer', example: 10, nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Submit Categories successfully', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    private function opPOSTAdminCategories_5afd80(): void {}

    #[OA\Post(
        path: '/v1/admin/categories/create',
        summary: 'Create category with icon',
        tags: ['Admin'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['name', 'icon'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Plumbers', maxLength: 255),
                        new OA\Property(property: 'subcategories', type: 'string', example: 'Emergency,Residential', nullable: true),
                        new OA\Property(property: 'icon', description: 'Category icon (PNG or SVG, max 2MB)', type: 'string', format: 'binary'),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Category created successfully', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    private function opPOSTAdminCategoriesCreate_f9b6df(): void {}

    #[OA\Post(
        path: '/v1/admin/categories/view',
        summary: 'Submit View',
        tags: ['Admin'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id'],
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Submit View successfully', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    private function opPOSTAdminCategoriesView_a07b2a(): void {}

    #[OA\Post(
        path: '/v1/admin/categories/update',
        summary: 'Update category (optional icon)',
        tags: ['Admin'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['id', 'name'],
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Plumbers', maxLength: 255),
                        new OA\Property(property: 'subcategories', type: 'string', example: 'Emergency,Residential', nullable: true),
                        new OA\Property(property: 'icon', description: 'Optional replacement icon (PNG or SVG, max 2MB)', type: 'string', format: 'binary', nullable: true),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Category updated successfully', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    private function opPOSTAdminCategoriesUpdate_c2725f(): void {}

    #[OA\Post(
        path: '/v1/admin/categories/delete',
        summary: 'Submit Delete',
        tags: ['Admin'],
        security: [['passport' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id'],
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Submit Delete successfully', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    private function opPOSTAdminCategoriesDelete_9b97c1(): void {}
}
