<?php

namespace App\Http\Requests\Concerns;

use App\Models\Category;
use Illuminate\Validation\Validator;

trait ValidatesBusinessSubcategory
{
    protected function validateBusinessSubcategory(Validator $validator, bool $requiredWhenAvailable = false): void
    {
        $validator->after(function (Validator $validator) use ($requiredWhenAvailable): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $categoryId = $this->input('category_id');
            if ($categoryId === null || $categoryId === '') {
                return;
            }

            $category = Category::query()->find((int) $categoryId);
            if ($category === null) {
                return;
            }

            $allowed = array_values(array_filter(
                is_array($category->subcategories) ? $category->subcategories : [],
                fn($value) => is_string($value) && trim($value) !== '',
            ));

            $subcategory = $this->input('subcategory');
            $subcategory = is_string($subcategory) ? trim($subcategory) : '';

            if ($allowed === []) {
                if ($subcategory !== '') {
                    $validator->errors()->add('subcategory', 'Subcategory is not available for the selected category.');
                }

                return;
            }

            if ($subcategory === '') {
                if ($requiredWhenAvailable) {
                    $validator->errors()->add('subcategory', 'Please select a subcategory.');
                }

                return;
            }

            if (! in_array($subcategory, $allowed, true)) {
                $validator->errors()->add('subcategory', 'Invalid subcategory for the selected category.');
            }
        });
    }
}
