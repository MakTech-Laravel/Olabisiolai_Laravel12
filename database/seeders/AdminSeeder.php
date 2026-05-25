<?php

namespace Database\Seeders;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::query()->updateOrCreate(
            ['email' => 'admin@dev.com'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'name' => 'System Admin',
                'phone' => '+2348000000001',
                'status' => AdminStatus::Active->value,
                'email_verified_at' => now(),
                'password' => Hash::make('admin@dev.com'),
            ]
        );

        Admin::query()->updateOrCreate(
            ['email' => 'superadmin@dev.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'name' => 'Super Admin',
                'phone' => '+2348000000002',
                'status' => AdminStatus::Active->value,
                'email_verified_at' => now(),
                'password' => Hash::make('superadmin@dev.com'),
            ]
        );
    }
}
