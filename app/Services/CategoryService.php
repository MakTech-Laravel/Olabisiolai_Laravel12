<?php

namespace App\Services;

use App\Http\Traits\FileManagementTrait;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    use FileManagementTrait;

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

    public function createCategory(array $validated, ?UploadedFile $icon = null): Category
    {
        $iconPath = null;
        if ($icon !== null) {
            $iconPath = $this->handleFileUpload($icon, 'categories/icons', 'category-icon');
        }

        return Category::query()->create([
            'name' => trim((string) $validated['name']),
            'subcategories' => $this->normalizeSubcategories($validated['subcategories'] ?? null),
            'icon' => $iconPath,
        ]);
    }

    public function getCategoryById(int $categoryId): Category
    {
        return Category::query()->findOrFail($categoryId);
    }

    public function updateCategory(Category $category, array $validated, ?UploadedFile $icon = null): Category
    {
        $iconPath = $category->icon;

        if ($icon !== null) {
            $this->fileDelete($category->icon);
            $iconPath = $this->handleFileUpload($icon, 'categories/icons', 'category-icon');
        }

        $category->update([
            'name' => trim((string) $validated['name']),
            'subcategories' => $this->normalizeSubcategories($validated['subcategories'] ?? null),
            'icon' => $iconPath,
        ]);

        return $category->fresh();
    }

    public function deleteCategory(Category $category): void
    {
        $this->fileDelete($category->icon);
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
