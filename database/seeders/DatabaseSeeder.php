<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Production deploy: run only the "reference data" block below, then create
     * real admin accounts manually (do not rely on @dev.com credentials).
     * Uncomment the "local demo data" block only for local/staging QA.
     */
    public function run(): void
    {
        // --- Reference data (safe for production) ---
        $this->call([
            PassportClientSeeder::class,
            RolePermissionSeeder::class,
            LocationSeeder::class,
            PricingPackageSeeder::class,
            CategorySeeder::class,
            CmsPageSeeder::class,
        ]);

        // --- Local demo data (disabled for production onboarding) ---
        // $this->call([
        //     AdminSeeder::class,
        //     UsersSeeder::class,
        //     FeaturedBusinessSeeder::class,
        //     BusinessSocialAccountsSeeder::class,
        //     BusinessHoursSeeder::class,
        // ]);

        // --- Local messaging / presence demos ---
        // if (! app()->environment('production')) {
        //     $this->call([
        //         ConversationSeeder::class,
        //         MessageSeeder::class,
        //         UserStatusSeeder::class,
        //     ]);
        // }
    }
}
