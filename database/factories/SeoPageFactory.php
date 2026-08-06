<?php

namespace Database\Factories;

use App\Models\SeoPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoPage>
 */
class SeoPageFactory extends Factory
{
    protected $model = SeoPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => '/'.fake()->unique()->slug(),
            'page_name' => fake()->words(3, true),
            'meta_title' => fake()->sentence(4),
            'meta_description' => fake()->sentence(12),
            'meta_keywords' => implode(', ', fake()->words(4)),
            'changefreq' => 'weekly',
            'priority' => 0.7,
        ];
    }
}
