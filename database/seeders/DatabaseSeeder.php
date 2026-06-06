<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PassportClientSeeder::class,
            AdminSeeder::class,
            // Spatie roles + permissions + super-admin assignment (must run after admins exist).
            RolePermissionSeeder::class,
            LocationSeeder::class,
            PricingPackageSeeder::class,
            CategorySeeder::class,
            UsersSeeder::class,
            CmsPageSeeder::class,
            FeaturedBusinessSeeder::class,
            BusinessSocialAccountsSeeder::class,
            BusinessHoursSeeder::class,
        ]);

        // if (! app()->environment('production')) {
        //     $this->call([
        //         ConversationSeeder::class,
        //         MessageSeeder::class,
        //         UserStatusSeeder::class,
        //     ]);
        // }
    }
}
