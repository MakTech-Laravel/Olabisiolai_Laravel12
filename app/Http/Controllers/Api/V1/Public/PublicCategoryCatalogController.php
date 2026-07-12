<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Unauthenticated category list for storefront filters and forms.
 */
class PublicCategoryCatalogController extends Controller
{
    #[OA\Get(
        path: '/v1/categories',
        summary: 'List all business categories',
        tags: ['Public'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Categories retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'categories', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'count', type: 'integer'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function index()
    {
        try {
            $categories = Category::query()
                ->orderBy('name')
                ->get(['id', 'name', 'subcategories', 'icon', 'created_at', 'updated_at']);

            return sendResponse(true, 'Categories retrieved successfully.', [
                'categories' => CategoryResource::collection($categories)->resolve(),
                'count' => $categories->count(),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Get(
        path: '/v1/categories/{category}',
        summary: 'Get a single category',
        tags: ['Public'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Category retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'category', type: 'object'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 404, description: 'Category not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function show(Category $category)
    {
        try {
            return sendResponse(true, 'Category retrieved successfully.', [
                'category' => new CategoryResource($category),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
