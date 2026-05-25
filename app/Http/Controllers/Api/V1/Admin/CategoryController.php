<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categoryService) {}

    public function allCategories(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'search' => ['nullable', 'string', 'max:255'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            $categories = $this->categoryService->paginateCategories(
                isset($validated['search']) ? (string) $validated['search'] : null,
                $validated['per_page'] ?? 10
            );

            if ($categories->total() === 0) {
                return sendResponse(true, 'No categories found.', [
                    'filter' => [
                        'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
                    ],
                    'count' => 0,
                    'pagination' => [
                        'current_page' => $categories->currentPage(),
                        'per_page' => $categories->perPage(),
                        'last_page' => $categories->lastPage(),
                        'total' => 0,
                    ],
                    'categories' => [],
                ]);
            }

            return sendResponse(true, 'Categories retrieved successfully.', [
                'filter' => [
                    'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
                ],
                'count' => $categories->total(),
                'pagination' => [
                    'current_page' => $categories->currentPage(),
                    'per_page' => $categories->perPage(),
                    'last_page' => $categories->lastPage(),
                    'total' => $categories->total(),
                ],
                'categories' => CategoryResource::collection($categories),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function createCategory(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
                'subcategories' => ['nullable'],
            ]);

            $category = $this->categoryService->createCategory($validated);

            return sendResponse(true, 'Category created successfully.', [
                'category' => new CategoryResource($category),
            ], Response::HTTP_CREATED);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function viewCategory(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:categories,id'],
            ]);

            $categoryId = $this->resolveCategoryId($validated);
            $category = $this->categoryService->getCategoryById($categoryId);

            return sendResponse(true, 'Category retrieved successfully.', [
                'category' => new CategoryResource($category),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateCategory(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:categories,id'],
                'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($request->input('id'))],
                'subcategories' => ['nullable'],
            ]);

            $categoryId = $this->resolveCategoryId($validated);
            $category = $this->categoryService->getCategoryById($categoryId);
            $category = $this->categoryService->updateCategory($category, $validated);

            return sendResponse(true, 'Category updated successfully.', [
                'category' => new CategoryResource($category),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteCategory(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:categories,id'],
            ]);

            $categoryId = $this->resolveCategoryId($validated);
            $category = $this->categoryService->getCategoryById($categoryId);
            $this->categoryService->deleteCategory($category);

            return sendResponse(true, 'Category deleted successfully.');
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function resolveCategoryId(array $validated): int
    {
        $categoryId = $validated['id'] ?? null;

        if ($categoryId === null) {
            throw ValidationException::withMessages([
                'id' => ['The id field is required.'],
            ]);
        }

        return (int) $categoryId;
    }
}
