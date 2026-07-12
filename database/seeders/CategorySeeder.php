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
     * Icon file (relative to public/images/categories) for each category.
     *
     * @return array<string, string>
     */
    public static function categoryIcons(): array
    {
        return [
            'Plumbers' => 'wrench-alt.svg',
            'Electricians' => 'zap.svg',
            'Cleaners' => 'brush-cleaning.svg',
            'Painters' => 'paintbrush-vertical.svg',
            'Carpenters' => 'hard-hat.svg',
            'Tilers' => 'layout-grid.svg',
            'AC Technicians' => 'AC Technicians.png',
            'Handymen' => 'tools.png',
            'Barbers' => 'scissors.svg',
            'Hair Stylists' => 'sparkles.svg',
            'Makeup Artists' => 'palette.svg',
            'Nail Technicians' => 'hand.svg',
            'Spa & Massage' => 'flower-2.svg',
            'Skincare Specialists' => 'droplet.svg',
            'Dispatch Riders' => 'bike.svg',
            'Movers' => 'truck.svg',
            'Errand Services' => 'map-pin.svg',
            'Courier Services' => 'package.svg',
            'Caterers' => 'utensils-crossed.svg',
            'Bakers' => 'croissant.svg',
            'Small Chops Vendors' => 'utensils.svg',
            'Private Chefs' => 'chef-hat.svg',
            'Event Planners' => 'calendar.svg',
            'Photographers' => 'camera.svg',
            'Videographers' => 'video.svg',
            'MCs & Hosts' => 'mic.svg',
            'Decorators' => 'party-popper.svg',
            'DJ Services' => 'headphones.svg',
            'Tutors' => 'graduation-cap.svg',
            'Language Teachers' => 'languages.svg',
            'Skill Trainers' => 'book-open.svg',
            'Music Instructors' => 'music.svg',
            'Lawyers' => 'scale.svg',
            'Accountants' => 'calculator.svg',
            'Consultants' => 'briefcase.svg',
            'Real Estate Agents' => 'house.svg',
            'Insurance Agents' => 'shield.svg',
            'Tailors' => 'shirt.svg',
            'Fashion Designers' => 'ruler.svg',
            'Dry Cleaners' => 'washing-machine.svg',
            'Shoe Makers' => 'footprints.svg',
            'Nannies' => 'baby.svg',
            'Home Tutors' => 'notebook-pen.svg',
            'Party Rentals' => 'tent.svg',
            'Fitness Trainers' => 'dumbbell.svg',
            'Dieticians' => 'salad.svg',
            'Therapists' => 'heart-handshake.svg',
            'Office Cleaners' => 'spray-can.svg',
            'Facility Managers' => 'building-2.svg',
            'Security Services' => 'shield-check.svg',
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $definitions = self::categoryDefinitions();
        $icons = self::categoryIcons();
        $allowedNames = array_keys($definitions);

        foreach ($definitions as $name => $subcategories) {
            $icon = $icons[$name] ?? null;

            Category::query()->updateOrCreate(
                ['name' => $name],
                [
                    'subcategories' => array_values($subcategories),
                    'icon' => $icon !== null ? '/images/categories/'.$icon : null,
                ],
            );
        }

        Category::query()
            ->whereNotIn('name', $allowedNames)
            ->whereDoesntHave('businessInfos')
            ->delete();
    }
}
