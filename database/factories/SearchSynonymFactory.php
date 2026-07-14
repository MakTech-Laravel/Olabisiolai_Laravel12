<?php

namespace Database\Factories;

use App\Models\SearchSynonym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchSynonym>
 */
class SearchSynonymFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term' => fake()->unique()->word(),
            'synonyms' => [fake()->word(), fake()->word()],
        ];
    }
}
