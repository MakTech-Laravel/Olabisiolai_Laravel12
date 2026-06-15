<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Marketplace business types from client spec (15-olabisiolai-conversation.txt).
     * Each entry is a top-level category; subcategories are not used for this catalog.
     *
     * @return list<string>
     */
    public static function categoryNames(): array
    {
        return [
            'Plumbers',
            'Electricians',
            'Cleaners',
            'Painters',
            'Carpenters',
            'Tilers',
            'AC Technicians',
            'Handymen',
            'Barbers',
            'Hair Stylists',
            'Makeup Artists',
            'Nail Technicians',
            'Spa & Massage',
            'Skincare Specialists',
            'Dispatch Riders',
            'Movers',
            'Errand Services',
            'Courier Services',
            'Caterers',
            'Bakers',
            'Small Chops Vendors',
            'Private Chefs',
            'Event Planners',
            'Photographers',
            'Videographers',
            'MCs & Hosts',
            'Decorators',
            'DJ Services',
            'Tutors',
            'Language Teachers',
            'Skill Trainers',
            'Music Instructors',
            'Lawyers',
            'Accountants',
            'Consultants',
            'Real Estate Agents',
            'Insurance Agents',
            'Tailors',
            'Fashion Designers',
            'Dry Cleaners',
            'Shoe Makers',
            'Nannies',
            'Home Tutors',
            'Party Rentals',
            'Fitness Trainers',
            'Dieticians',
            'Therapists',
            'Office Cleaners',
            'Facility Managers',
            'Security Services',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function categoryDefinitions(): array
    {
        $definitions = [];

        foreach (self::categoryNames() as $name) {
            $definitions[$name] = [];
        }

        return $definitions;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $definitions = self::categoryDefinitions();
        $allowedNames = array_keys($definitions);

        foreach ($definitions as $name => $subcategories) {
            Category::query()->updateOrCreate(
                ['name' => $name],
                ['subcategories' => array_values($subcategories)],
            );
        }

        Category::query()
            ->whereNotIn('name', $allowedNames)
            ->whereDoesntHave('businessInfos')
            ->delete();
    }
}
