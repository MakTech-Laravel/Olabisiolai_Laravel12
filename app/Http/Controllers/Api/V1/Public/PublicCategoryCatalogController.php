<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Response;
use Throwable;

/**
 * Unauthenticated category list for storefront filters and forms.
 */
class PublicCategoryCatalogController extends Controller
{
    public function index()
    {
        try {
            $categories = Category::query()
                ->orderBy('name')->limit(10)
                ->get(['id', 'name', 'subcategories', 'created_at', 'updated_at']);

            return sendResponse(true, 'Categories retrieved successfully.', [
                'categories' => CategoryResource::collection($categories)->resolve(),
                'count' => $categories->count(),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

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
