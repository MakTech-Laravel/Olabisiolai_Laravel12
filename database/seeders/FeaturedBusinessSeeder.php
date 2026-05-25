<?php

namespace Database\Seeders;

use App\Enums\BusinessStatus;
use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Uses {@see CategorySeeder} rows only: resolves their primary keys and sets `category_id` on each featured business.
 * Does not create categories.
 */
class FeaturedBusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categoriesByName = $this->resolveCategoriesFromSeeder();

        $homeRepairId = $this->categoryId($categoriesByName, 'Home Repair Services');
        $fashionBeautyId = $this->categoryId($categoriesByName, 'Fashion & Beauty Services');
        $eventServicesId = $this->categoryId($categoriesByName, 'Event Services');
        $techRepairId = $this->categoryId($categoriesByName, 'Tech Repair Services');
        $foodStayId = $this->categoryId($categoriesByName, 'Food & Stay');

        $featuredBusinesses = [
            [
                'name' => 'Sparkle Clean Services',
                'category_id' => $homeRepairId,
                'services' => ['Cleaning Services'],
                'location' => 'Lagos, Surulere',
                'rating' => 4.9,
                'reviews' => 203,
                'description' => 'Professional cleaning services for homes and offices. Eco-friendly products available.',
                'image' => '/images/feature/1-1.jpg',
                'verified' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Elite Electrical Solutions',
                'category_id' => $homeRepairId,
                'services' => ['Electrician'],
                'location' => 'Lagos, Victoria Island',
                'rating' => 4.6,
                'reviews' => 89,
                'description' => 'Certified electricians providing safe and reliable electrical installations and repairs.',
                'image' => '/images/feature/1-2.jpg',
                'verified' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Glamour Beauty Spa',
                'category_id' => $fashionBeautyId,
                'services' => ['Spa'],
                'location' => 'Lagos, Lekki',
                'rating' => 4.7,
                'reviews' => 156,
                'description' => 'Luxury spa and beauty treatments in a relaxing environment.',
                'image' => '/images/feature/1-3.jpg',
                'verified' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Royal Catering & Events',
                'category_id' => $eventServicesId,
                'services' => ['Caterer', 'Event Planner'],
                'location' => 'Lagos, Lekki',
                'rating' => 4.9,
                'reviews' => 178,
                'description' => 'Full-service catering for weddings, corporate events, and private parties with local and international cuisines.',
                'image' => '/images/feature/1-4.jpg',
                'verified' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Vision Events & Decor',
                'category_id' => $eventServicesId,
                'services' => ['Event Decorator', 'Photographer'],
                'location' => 'Abuja, Wuse',
                'rating' => 4.5,
                'reviews' => 92,
                'description' => 'Event styling, floral design, and creative direction for memorable celebrations.',
                'image' => '/images/feature/1-5.jpg',
                'verified' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Tech Solutions Pro',
                'category_id' => $techRepairId,
                'services' => ['Laptop Repairer', 'Phone Repairer'],
                'location' => 'Lagos, Victoria Island',
                'rating' => 4.8,
                'reviews' => 145,
                'description' => 'Device repairs, diagnostics, and on-site support for phones, laptops, and small business IT.',
                'image' => '/images/feature/1-6.jpg',
                'verified' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Premium Plumbing Services',
                'category_id' => $homeRepairId,
                'services' => ['Plumber'],
                'location' => 'Lagos, Ikeja',
                'rating' => 4.8,
                'reviews' => 127,
                'description' => 'Professional plumbing services for residential and commercial properties. Available 24/7 for emergencies.',
                'image' => '/images/feature/1.jpg',
                'verified' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Midnight Mixology Lounge',
                'category_id' => $foodStayId,
                'services' => ['Mixologist', 'Bar'],
                'location' => 'Lagos, Yaba',
                'rating' => 4.7,
                'reviews' => 112,
                'description' => 'Craft cocktails, mocktails, and private bar experiences for events and venues.',
                'image' => '/images/feature/1-2.jpg',
                'verified' => true,
                'sort_order' => 9,
            ],
        ];

        foreach ($featuredBusinesses as $data) {
            $locationParts = explode(', ', $data['location']);
            $stateName = $locationParts[0] ?? 'Lagos';
            $cityLgaName = $locationParts[1] ?? 'Ikeja';

            $location = Location::firstOrCreate([
                'state_name' => $stateName,
                'lga_name' => $cityLgaName,
            ], [
                'country_name' => 'Nigeria',
                'country_iso_code' => 'NG',
                'latitude' => 6.5244,
                'longitude' => 3.3792,
                'city_name' => $cityLgaName,
            ]);

            $vendor = User::factory()->create([
                'first_name' => explode(' ', $data['name'])[0],
                'last_name' => 'Vendor',
                'email' => Str::slug($data['name']) . '-' . Str::random(5) . '@example.com',
                'role' => 'vendor',
            ]);

            $business = BusinessInfo::create([
                'user_id' => $vendor->id,
                'category_id' => $data['category_id'],
                'location_id' => $location->id,
                'business_name' => $data['name'],
                'business_description' => $data['description'],
                'services_offered' => $data['services'],
                'cover_photo_paths' => [],
                'phone' => '+234' . rand(7000000000, 9999999999),
                'logo_path' => $data['image'],
                'verification_status' => $data['verified'] ? VerificationStatus::Approved->value : VerificationStatus::None->value,
                'business_status' => BusinessStatus::Active->value,
                'verified_at' => $data['verified'] ? now() : null,
                'sort_order' => $data['sort_order'],
            ]);

            for ($i = 0; $i < 2; $i++) {
                $rating = $this->generateRating($data['rating']);

                $reviewer = User::factory()->create([
                    'name' => fake()->name(),
                    'email' => fake()->unique()->safeEmail(),
                    'email_verified_at' => now(),
                ]);

                $business->reviews()->create([
                    'business_id' => $business->id,
                    'user_id' => $reviewer->id,
                    'full_name' => $reviewer->name,
                    'rating' => $rating,
                    'review_text' => fake()->paragraph(rand(2, 5)),
                    'is_approved' => true,
                    'created_at' => fake()->dateTimeBetween('-6 months', 'now'),
                ]);
            }
        }
    }

    /**
     * Load categories created by CategorySeeder (same names as {@see CategorySeeder::categoryDefinitions()}).
     *
     * @return Collection<string, Category>
     */
    private function resolveCategoriesFromSeeder(): Collection
    {
        $expectedNames = array_keys(CategorySeeder::categoryDefinitions());

        $categoriesByName = Category::query()
            ->whereIn('name', $expectedNames, 'and', false)
            ->get()
            ->keyBy('name');

        foreach ($expectedNames as $name) {
            if (! $categoriesByName->has($name)) {
                throw new RuntimeException(
                    "Missing category \"{$name}\". Run CategorySeeder before FeaturedBusinessSeeder."
                );
            }
        }

        return $categoriesByName;
    }

    /**
     * Resolve primary key for a category row created by {@see CategorySeeder}.
     *
     * @param  Collection<string, Category>  $categoriesByName
     */
    private function categoryId(Collection $categoriesByName, string $definitionName): int
    {
        $category = $categoriesByName->get($definitionName);

        if ($category === null) {
            throw new RuntimeException(
                "Category \"{$definitionName}\" is missing. It must exist in CategorySeeder::categoryDefinitions() and be seeded before FeaturedBusinessSeeder."
            );
        }

        return $category->id;
    }

    /**
     * Generate a rating around the target.
     */
    private function generateRating(float $target): int
    {
        $min = max(1, floor($target));
        $max = min(5, ceil($target));

        return (rand(0, 100) > 70) ? (int) $min : (int) $max;
    }
}
