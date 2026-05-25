<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminAccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_block_another_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $super = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        $target = Admin::query()->create([
            'first_name' => 'T',
            'last_name' => 'One',
            'name' => 'T One',
            'email' => 'target@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);
        $target->assignRole('editor-unit');

        Passport::actingAs($super, [], 'admin_api');

        $this->putJson("/api/v1/admin/admins/{$target->id}/status", ['status' => 'block'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($target->fresh()->status === AdminStatus::Block);
    }

    public function test_editor_unit_without_permission_cannot_change_status(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $actor = Admin::query()->create([
            'first_name' => 'M',
            'last_name' => 'Actor',
            'name' => 'M Actor',
            'email' => 'mgractor@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);
        $actor->assignRole('editor-unit');

        $target = Admin::query()->create([
            'first_name' => 'T',
            'last_name' => 'Two',
            'name' => 'T Two',
            'email' => 'target2@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);
        $target->assignRole('editor-unit');

        Passport::actingAs($actor, [], 'admin_api');

        $this->putJson("/api/v1/admin/admins/{$target->id}/status", ['status' => 'block'])
            ->assertForbidden();
    }

    public function test_editor_unit_with_permission_can_change_status(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $actor = Admin::query()->create([
            'first_name' => 'M',
            'last_name' => 'Actor',
            'name' => 'M Actor2',
            'email' => 'mgractor2@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);
        $actor->assignRole('editor-unit');
        $actor->givePermissionTo('change admin status');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $target = Admin::query()->create([
            'first_name' => 'T',
            'last_name' => 'Three',
            'name' => 'T Three',
            'email' => 'target3@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);
        $target->assignRole('editor-unit');

        Passport::actingAs($actor, [], 'admin_api');

        $this->putJson("/api/v1/admin/admins/{$target->id}/status", ['status' => 'pending'])
            ->assertOk();

        $fresh = $target->fresh();
        $this->assertTrue($fresh->status === AdminStatus::Pending);
        $this->assertNull($fresh->email_verified_at);
    }

    public function test_setting_active_marks_email_verified(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $super = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();

        $target = Admin::query()->create([
            'first_name' => 'T',
            'last_name' => 'Pending',
            'name' => 'T Pending',
            'email' => 'pendingadmin@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Pending,
            'email_verified_at' => null,
        ]);
        $target->assignRole('editor-unit');

        Passport::actingAs($super, [], 'admin_api');

        $this->putJson("/api/v1/admin/admins/{$target->id}/status", ['status' => 'active'])
            ->assertOk();

        $fresh = $target->fresh();
        $this->assertTrue($fresh->status === AdminStatus::Active);
        $this->assertNotNull($fresh->email_verified_at);
    }

    public function test_cannot_change_own_status(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $super = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();

        Passport::actingAs($super, [], 'admin_api');

        $this->putJson("/api/v1/admin/admins/{$super->id}/status", ['status' => 'block'])
            ->assertUnprocessable();
    }

    public function test_editor_unit_cannot_change_super_admin_status(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $super = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();

        $actor = Admin::query()->create([
            'first_name' => 'M',
            'last_name' => 'Actor',
            'name' => 'M Actor3',
            'email' => 'mgractor3@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);
        $actor->assignRole('editor-unit');
        $actor->givePermissionTo('change admin status');

        Passport::actingAs($actor, [], 'admin_api');

        $this->putJson("/api/v1/admin/admins/{$super->id}/status", ['status' => 'block'])
            ->assertForbidden();
    }
}
