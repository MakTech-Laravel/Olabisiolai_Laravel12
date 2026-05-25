<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed marketplace categories and their subcategories (services / business types).
     *
     * @return array<string, list<string>>
     */
    public static function categoryDefinitions(): array
    {
        return [
            'Event Services' => [
                'Event Planner',
                'Caterer',
                'Makeup Artist',
                'Photographer',
                'Videographer',
                'Event Decorator',
                'DJ',
                'MC / Host',
                'Surprise Vendor',
                'Others',
            ],
            'Fashion & Beauty Services' => [
                'Tailor',
                'Hair Stylist',
                'Nail Technician',
                'Skincare Specialist',
                'Barber',
                'Fashion Designer',
                'Spa',
                'Others',
            ],
            'Food & Stay' => [
                'Baker',
                'Home Chef',
                'Cocktails and Mocktails Vendor',
                'Smoothie Vendor',
                'Mixologist',
                'Restaurants',
                'Bar',
                'Hotel',
                'Breakfast & Brunch Spots',
                'Street Food Vendors',
                'Others',
            ],
            'Shopping' => [
                'Fashion Stores',
                'Gift Shops',
                'Accessories Stores',
                'Beauty Stores',
                'Others',
            ],
            'Home & Decor Shops' => [
                'Home Decor',
                'Furniture',
                'Lighting',
                'Others',
            ],
            'Home Repair Services' => [
                'Electrician',
                'Plumber',
                'AC & Fridge Repairer',
                'Cleaning Services',
                'Fumigation Services',
                'Others',
            ],
            'Tech Repair Services' => [
                'Phone Repairer',
                'Laptop Repairer',
                'CCTV Installer',
                'Solar & Inverter Technician',
                'Others',
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::categoryDefinitions() as $name => $subcategories) {
            Category::query()->updateOrCreate(
                ['name' => $name],
                ['subcategories' => array_values($subcategories)]
            );
        }
    }
}
