<?php

namespace App\Services;

use App\Http\Traits\FileManagementTrait;
use App\Models\BusinessInfo;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    use FileManagementTrait;

    public function paginateCategories(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return Category::query()
            ->orderByHigherBusinessCount()
            ->withCount('businessInfos')
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

        $category = Category::query()->create([
            'name' => trim((string) $validated['name']),
            'subcategories' => $this->normalizeSubcategories($validated['subcategories'] ?? null),
            'icon' => $iconPath,
        ]);

        return $category->loadCount('businessInfos');
    }

    public function getCategoryById(int $categoryId): Category
    {
        return Category::query()->withCount('businessInfos')->findOrFail($categoryId);
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

        return $category->fresh()->loadCount('businessInfos');
    }

    /**
     * Delete a category. If it still has businesses, they must be moved to another category first.
     */
    public function deleteCategory(Category $category, ?int $moveToCategoryId = null): void
    {
        $businessCount = (int) $category->businessInfos()->count();

        if ($businessCount > 0) {
            if ($moveToCategoryId === null) {
                throw ValidationException::withMessages([
                    'move_to_category_id' => [
                        "This category has {$businessCount} business(es). Select another category to move them to before deleting.",
                    ],
                ]);
            }

            if ($moveToCategoryId === (int) $category->id) {
                throw ValidationException::withMessages([
                    'move_to_category_id' => ['Choose a different category to move businesses to.'],
                ]);
            }

            $target = Category::query()->find($moveToCategoryId);
            if ($target === null) {
                throw ValidationException::withMessages([
                    'move_to_category_id' => ['The selected category does not exist.'],
                ]);
            }

            DB::transaction(function () use ($category, $target): void {
                $this->reassignBusinessesToCategory($category, $target);
                $this->fileDelete($category->icon);
                $category->delete();
            });

            return;
        }

        $this->fileDelete($category->icon);
        $category->delete();
    }

    private function reassignBusinessesToCategory(Category $from, Category $to): void
    {
        $allowedSubcategories = collect($to->subcategories ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->all();

        $from->businessInfos()->each(function (BusinessInfo $business) use ($to, $allowedSubcategories): void {
            $subcategory = is_string($business->subcategory) ? trim($business->subcategory) : '';

            $business->category_id = $to->id;
            if ($subcategory === '' || ! in_array($subcategory, $allowedSubcategories, true)) {
                $business->subcategory = null;
            }
            $business->save();
        });
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
