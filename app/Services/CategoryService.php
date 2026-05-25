<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function paginateCategories(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return Category::query()
            ->when($search !== null && trim($search) !== '', function ($query) use ($search) {
                $keyword = trim($search);

                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('name', 'like', "%{$keyword}%")
                        ->orWhere('subcategories', 'like', "%{$keyword}%");
                });
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createCategory(array $validated): Category
    {
        return Category::query()->create([
            'name' => trim((string) $validated['name']),
            'subcategories' => $this->normalizeSubcategories($validated['subcategories'] ?? null),
        ]);
    }

    public function getCategoryById(int $categoryId): Category
    {
        return Category::query()->findOrFail($categoryId);
    }

    public function updateCategory(Category $category, array $validated): Category
    {
        $category->update([
            'name' => trim((string) $validated['name']),
            'subcategories' => $this->normalizeSubcategories($validated['subcategories'] ?? null),
        ]);

        return $category->fresh();
    }

    public function deleteCategory(Category $category): void
    {
        $category->delete();
    }

    private function normalizeSubcategories(mixed $input): array
    {
        if ($input === null) {
            return [];
        }

        if (is_string($input)) {
            $parts = explode(',', $input);

            return collect($parts)
                ->map(fn ($item) => trim((string) $item))
                ->filter(fn ($item) => $item !== '')
                ->values()
                ->all();
        }

        if (is_array($input)) {
            return collect($input)
                ->map(fn ($item) => trim((string) $item))
                ->filter(fn ($item) => $item !== '')
                ->values()
                ->all();
        }

        throw ValidationException::withMessages([
            'subcategories' => ['The subcategories field must be an array or a comma separated string.'],
        ]);
    }
}
