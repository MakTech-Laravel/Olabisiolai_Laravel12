<?php

namespace Database\Factories;

use App\Enums\CmsPageType;
use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsPage>
 */
class CmsPageFactory extends Factory
{
    protected $model = CmsPage::class;

    public function definition(): array
    {
        return [
            'type' => CmsPageType::AboutUs,
            'title' => fake()->sentence(3),
            'description' => '<p>' . fake()->paragraph() . '</p>',
        ];
    }

    public function type(CmsPageType $type): static
    {
        return $this->state(fn() => ['type' => $type]);
    }
}
