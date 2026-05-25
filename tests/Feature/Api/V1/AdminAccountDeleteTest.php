<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminAccountDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_delete_another_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $super = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        $target = Admin::query()->create([
            'first_name' => 'Del',
            'last_name' => 'Target',
            'name' => 'Del Target',
            'email' => 'delete-me@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);
        $target->assignRole('editor-unit');

        Passport::actingAs($super, [], 'admin_api');

        $this->deleteJson("/api/v1/admin/admins/{$target->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('admins', ['id' => $target->id]);
    }

    public function test_cannot_delete_own_account(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $super = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();

        Passport::actingAs($super, [], 'admin_api');

        $this->deleteJson("/api/v1/admin/admins/{$super->id}")
            ->assertUnprocessable();
    }

    public function test_editor_with_delete_permission_can_delete_non_super_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $actor = Admin::query()->create([
            'first_name' => 'E',
            'last_name' => 'D',
            'name' => 'E D',
            'email' => 'editor-delete@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);
        $actor->assignRole('editor-unit');
        $actor->givePermissionTo('delete admins');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $target = Admin::query()->create([
            'first_name' => 'T',
            'last_name' => 'Gone',
            'name' => 'T Gone',
            'email' => 'gone@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);
        $target->assignRole('support-staff');

        Passport::actingAs($actor, [], 'admin_api');

        $this->deleteJson("/api/v1/admin/admins/{$target->id}")
            ->assertOk();

        $this->assertDatabaseMissing('admins', ['id' => $target->id]);
    }

    public function test_editor_cannot_delete_super_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $super = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();

        $actor = Admin::query()->create([
            'first_name' => 'E',
            'last_name' => '2',
            'name' => 'E 2',
            'email' => 'editor-delete2@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);
        $actor->assignRole('editor-unit');
        $actor->givePermissionTo('delete admins');

        Passport::actingAs($actor, [], 'admin_api');

        $this->deleteJson("/api/v1/admin/admins/{$super->id}")
            ->assertForbidden();
    }
}
