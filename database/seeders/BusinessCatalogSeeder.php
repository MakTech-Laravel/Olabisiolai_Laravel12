<?php

namespace Database\Seeders;

use App\Enums\BusinessStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\BusinessCatalogService;
use Database\Seeders\Support\SocialAccountSeedCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeds demo catalog items for user id 100 with Faker copy and
 * copyright-free Lorem Picsum image URLs (https://picsum.photos).
 */
class BusinessCatalogSeeder extends Seeder
{
    private const TARGET_USER_ID = 100;

    public function run(): void
    {
        $faker = fake();
        $business = $this->resolveBusinessForUser(self::TARGET_USER_ID);
        $maxItems = BusinessCatalogService::MAX_ITEMS_PER_BUSINESS;

        $existing = $business->catalogItems()->count();
        if ($existing >= $maxItems) {
            $this->command?->info("Business #{$business->id} already has {$existing} catalog items — skipping.");

            return;
        }

        $toCreate = $maxItems - $existing;
        $serviceNames = [
            'Home consultation',
            'On-site repair',
            'Premium detailing',
            'Express delivery',
            'Weekend booking',
            'Full inspection',
            'Maintenance package',
            'Custom fitting',
            'Deep clean service',
            'Setup & training',
        ];
        $productNames = [
            'Starter kit',
            'Pro toolkit',
            'Replacement parts set',
            'Care bundle',
            'Gift pack',
            'Refill pack',
            'Accessory set',
            'Premium finish',
            'Travel size pack',
            'Family bundle',
        ];

        $rows = [];
        $now = now();

        for ($i = 0; $i < $toCreate; $i++) {
            $type = $faker->boolean(55) ? 'service' : 'product';
            $baseName = $type === 'service'
                ? $faker->randomElement($serviceNames)
                : $faker->randomElement($productNames);
            $name = trim($baseName.' '.$faker->unique()->words(2, true));
            $name = Str::limit($name, 120, '');

            $priceFrom = $faker->boolean(35);
            $priceKobo = $faker->numberBetween(15, 850) * 10000; // ₦1,500 – ₦85,000
            $imageSeed = 'gidira-u'.self::TARGET_USER_ID.'-'.$business->id.'-'.($existing + $i + 1);

            $rows[] = [
                'business_info_id' => $business->id,
                'type' => $type,
                'name' => $name,
                'description' => $faker->optional(0.85)->paragraphs(2, true),
                'price_kobo' => $priceKobo,
                'price_label' => null,
                'price_from' => $priceFrom,
                // Full HTTPS URLs are supported by public_media_url(); Lorem Picsum = free stock photos.
                'image_paths' => json_encode([
                    "https://picsum.photos/seed/{$imageSeed}/800/600",
                    "https://picsum.photos/seed/{$imageSeed}-b/800/600",
                ]),
                'sort_order' => $existing + $i + 1,
                'created_at' => $now->copy()->subDays($faker->numberBetween(0, 40)),
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 25) as $chunk) {
            BusinessCatalogItem::query()->insert($chunk);
        }

        $this->command?->info(
            "Seeded {$toCreate} catalog items for user #".self::TARGET_USER_ID
            ." (business #{$business->id} — {$business->business_name})."
        );
    }

    private function resolveBusinessForUser(int $userId): BusinessInfo
    {
        $user = User::query()->find($userId);

        if (! $user) {
            $user = User::query()->create([
                'id' => $userId,
                'first_name' => 'Catalog',
                'last_name' => 'Demo',
                'name' => 'Catalog Demo Vendor',
                'email' => 'catalog-demo-user-'.$userId.'@example.com',
                'phone' => '+2348'.str_pad((string) $userId, 9, '0', STR_PAD_LEFT),
                'role' => 'vendor',
                'status' => UserStatus::Active->value,
                'wants_marketing_emails' => false,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);

            $this->command?->warn("Created missing user #{$userId} (catalog-demo-user-{$userId}@example.com / password).");
        }

        /** @var BusinessInfo|null $business */
        $business = BusinessInfo::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->first();

        if ($business) {
            return $business;
        }

        $categoryId = Category::query()->value('id');
        $locationId = Location::query()->value('id');

        if ($categoryId === null || $locationId === null) {
            throw new RuntimeException(
                'Cannot create business for catalog seed: run CategorySeeder and LocationSeeder first.'
            );
        }

        $business = BusinessInfo::query()->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'location_id' => $locationId,
            'business_name' => 'Catalog Demo Business',
            'business_description' => 'Demo business used for catalog seed data (user '.$userId.').',
            'services_offered' => ['Products', 'Services'],
            'social_accounts' => SocialAccountSeedCatalog::forBusiness('Catalog Demo Business'),
            'phone' => $user->phone,
            'logo_path' => null,
            'cover_photo_paths' => [],
            'verification_status' => VerificationStatus::Approved->value,
            'business_status' => BusinessStatus::Active->value,
            'verified_at' => now(),
        ]);

        $this->command?->warn("Created business #{$business->id} for user #{$userId}.");

        return $business;
    }
}
