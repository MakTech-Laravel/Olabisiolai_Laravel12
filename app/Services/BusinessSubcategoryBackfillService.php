<?php

namespace App\Services;

use App\Models\BusinessInfo;
use App\Models\Category;
use App\Support\BusinessSubcategoryBackfillResult;
use App\Support\BusinessSubcategoryResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class BusinessSubcategoryBackfillService
{
    /**
     * @var array<int, array<string, string>> category_id => lowercase label => canonical label
     */
    private array $categoryLookups = [];

    public function run(int $chunkSize = 200): BusinessSubcategoryBackfillResult
    {
        $this->categoryLookups = $this->loadCategoryLookups();

        $totals = new BusinessSubcategoryBackfillResult();

        BusinessInfo::query()
            ->select(['id', 'category_id', 'subcategory', 'services_offered'])
            ->where(function ($query): void {
                $query->whereNull('subcategory')->orWhere('subcategory', '');
            })
            ->orderBy('id')
            ->chunkById($chunkSize, function (EloquentCollection $businesses) use (&$totals): void {
                $totals = $totals->merge($this->processChunk($businesses));
            });

        return $totals;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function loadCategoryLookups(): array
    {
        return Category::query()
            ->get(['id', 'subcategories'])
            ->mapWithKeys(function (Category $category): array {
                $allowed = is_array($category->subcategories) ? $category->subcategories : [];

                return [
                    (int) $category->id => BusinessSubcategoryResolver::normalizedLookup($allowed),
                ];
            })
            ->all();
    }

    /**
     * @param  EloquentCollection<int, BusinessInfo>  $businesses
     */
    private function processChunk(EloquentCollection $businesses): BusinessSubcategoryBackfillResult
    {
        $scanned = $businesses->count();
        $updatesBySubcategory = [];

        foreach ($businesses as $business) {
            $lookup = $this->categoryLookups[(int) $business->category_id] ?? [];
            $services = is_array($business->services_offered) ? $business->services_offered : [];

            $resolved = BusinessSubcategoryResolver::resolveFromLookup(
                null,
                $lookup,
                $services,
            );

            if ($resolved === null) {
                continue;
            }

            $updatesBySubcategory[$resolved][] = (int) $business->id;
        }

        $updated = 0;

        if ($updatesBySubcategory !== []) {
            DB::transaction(function () use ($updatesBySubcategory, &$updated): void {
                foreach ($updatesBySubcategory as $subcategory => $ids) {
                    $updated += BusinessInfo::query()
                        ->whereIn('id', $ids)
                        ->where(function ($query): void {
                            $query->whereNull('subcategory')->orWhere('subcategory', '');
                        })
                        ->update([
                            'subcategory' => $subcategory,
                            'updated_at' => now(),
                        ]);
                }
            });
        }

        return new BusinessSubcategoryBackfillResult(
            scanned: $scanned,
            updated: $updated,
            skipped: $scanned - $updated,
        );
    }
}
