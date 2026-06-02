<?php

namespace Database\Seeders;

use App\Enums\BusinessStatus;
use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\Support\SocialAccountSeedCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendor = User::query()->updateOrCreate(
            ['email' => 'vendor@dev.com'],
            [
                'first_name' => 'Default',
                'last_name' => 'Vendor',
                'name' => 'Default Vendor',
                'phone' => '+2348000000002',
                'role' => 'vendor',
                'wants_marketing_emails' => false,
                'email_verified_at' => now(),
                'password' => Hash::make('vendor@dev.com'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'user@dev.com'],
            [
                'first_name' => 'Default',
                'last_name' => 'Userf',
                'name' => 'Default Userf',
                'phone' => '+2348000000003',
                'role' => 'user',
                'image' => null,
                'wants_marketing_emails' => false,
                'email_verified_at' => now(),
                'password' => Hash::make('user@dev.com'),
            ]
        );

        $categoryId = Category::query()->value('id');
        $locationId = Location::query()->value('id');

        if ($categoryId !== null && $locationId !== null) {
            BusinessInfo::query()->updateOrCreate(
                ['user_id' => $vendor->id],
                [
                    'category_id' => $categoryId,
                    'location_id' => $locationId,
                    'business_name' => 'Vision Events & Decor',
                    'business_description' => 'Demo vendor business for messaging and listings.',
                    'services_offered' => ['Event planning', 'Decor'],
                    'social_accounts' => SocialAccountSeedCatalog::forBusiness('Vision Events & Decor'),
                    'phone' => '+2348000000002',
                    'logo_path' => 'businesses/sample/logo.png',
                    'cover_photo_paths' => [],
                    'verification_status' => VerificationStatus::Approved->value,
                    'business_status' => BusinessStatus::Active->value,
                    'verified_at' => now(),
                ]
            );
        }
    }
}
