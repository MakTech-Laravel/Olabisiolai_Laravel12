<?php

namespace Database\Seeders;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Permissions aligned with `routes/api/v1/admin.php` (+ legacy names used by the React admin UI).
        $permissions = [
            // Dashboard
            'view dashboard',

            // admin.php: GET admin/dashboard (placeholder)
            // admin.php: admin/users/*
            'view users',
            'edit users',
            'delete users',

            // admin.php: admin/categories/*
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',

            // admin.php: admin/business-info/* (businesses / marketplace)
            'view businesses',
            'create businesses',
            'edit businesses',
            'delete businesses',
            'bulk update businesses',
            'change business status',
            'message businesses',
            'view business statistics',

            // admin.php: admin/pricing/*
            'view pricing',
            'manage pricing',

            // admin.php: admin/verifications/*
            'view verifications',
            'manage verifications',
            'approve verifications',
            'reject verifications',
            'add verification notes',

            // admin.php: admin/locations/*
            'view locations',
            'create locations',
            'edit locations',
            'delete locations',
            'change location status',
            'toggle location boost',
            'view location vendors',
            'sync location vendors',

            // admin.php: admin/reviews/*
            'view reviews',
            'manage reviews',
            'edit reviews',
            'delete reviews',
            'bulk approve reviews',
            'bulk flag reviews',
            'view review statistics',

            // admin.php: admin/admins/* (middleware)
            'view admins',
            'create admins',
            'edit admins',
            'change admin status',
            'delete admins',

            // admin.php: admin/roles*, admin/permissions*
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'view permissions',

            // Legacy / sidebar: Businesses & Categories & Leads & Payments still gate on these
            'view products',
            'create products',
            'edit products',
            'delete products',
            'view orders',
            'edit orders',
            'delete orders',

            // Other admin modules (routes or UI may use later)
            'view boost',
            'manage boost',
            'view career',
            'manage career',
            'manage locations',
            'view notifications',
            'manage notifications',
            'view settings',
            'manage settings',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'admin',
            ]);
        }

        // Team hierarchy (display labels: Super Admin, Editor Unit, Support Staff, Verification Officer).
        $superAdmin = Role::query()->firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'admin',
        ]);
        // Super Admin — all pages / all admin-guard permissions.
        $superAdmin->syncPermissions(Permission::query()->where('guard_name', 'admin')->pluck('name')->all());

        // Editor Unit — Users, Reviews, Notifications.
        $editorUnit = Role::query()->firstOrCreate([
            'name' => 'editor-unit',
            'guard_name' => 'admin',
        ]);
        $editorUnit->syncPermissions([
            'view dashboard',
            'view users',
            'edit users',
            'view reviews',
            'manage reviews',
            'edit reviews',
            'delete reviews',
            'bulk approve reviews',
            'bulk flag reviews',
            'view review statistics',
            'view notifications',
            'manage notifications',
        ]);

        // Support Staff — Leads, Businesses, Payments follow-up.
        $supportStaff = Role::query()->firstOrCreate([
            'name' => 'support-staff',
            'guard_name' => 'admin',
        ]);
        $supportStaff->syncPermissions([
            'view dashboard',
            'view products',
            'edit products',
            'view businesses',
            'edit businesses',
            'change business status',
            'message businesses',
            'view business statistics',
            'view orders',
            'edit orders',
        ]);

        // Verification Officer — verification module only.
        $verificationOfficer = Role::query()->firstOrCreate([
            'name' => 'verification-officer',
            'guard_name' => 'admin',
        ]);
        $verificationOfficer->syncPermissions([
            'view dashboard',
            'view verifications',
            'manage verifications',
            'approve verifications',
            'reject verifications',
            'add verification notes',
        ]);

        $admin = Admin::query()->firstOrCreate(
            ['email' => 'superadmin@dev.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'name' => 'Super Admin',
                'password' => bcrypt('superadmin@dev.com'),
                'status' => AdminStatus::Active,
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->email_verified_at) {
            $admin->forceFill(['email_verified_at' => now()])->save();
        }

        $admin->assignRole('super-admin');
    }
}
