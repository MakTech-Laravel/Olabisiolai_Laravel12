<?php

namespace App\Services;

use App\Enums\CmsPageType;
use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Collection;

class CmsPageService
{
    /**
     * @return Collection<int, CmsPage>
     */
    public function all(): Collection
    {
        return CmsPage::query()
            ->orderBy('type')
            ->get();
    }

    public function getByType(CmsPageType $type): ?CmsPage
    {
        return CmsPage::query()
            ->where('type', $type->value)
            ->first();
    }

    public function upsertByType(CmsPageType $type, string $title, string $description): CmsPage
    {
        return CmsPage::query()->updateOrCreate(
            ['type' => $type->value],
            [
                'title' => $title,
                'description' => $description,
            ]
        );
    }
}
