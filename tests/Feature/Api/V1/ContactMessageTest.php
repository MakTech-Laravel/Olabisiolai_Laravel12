<?php

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use App\Models\ContactMessage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_public_can_submit_contact_message(): void
    {
        $response = $this->postJson('/api/v1/contact-messages', [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Pricing question',
            'message' => 'Hello, I need help with vendor plans.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('contact_messages', [
            'email' => 'jane@example.com',
            'subject' => 'Pricing question',
            'status' => 'new',
        ]);
    }

    public function test_admin_can_list_update_and_delete_contact_messages(): void
    {
        $message = ContactMessage::query()->create([
            'full_name' => 'John Smith',
            'email' => 'john@example.com',
            'subject' => 'Support',
            'message' => 'Need assistance.',
            'status' => ContactMessage::STATUS_NEW,
        ]);

        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        $list = $this->postJson('/api/v1/admin/contact-messages', []);
        $list->assertOk();
        $list->assertJsonPath('counts.all', 1);
        $list->assertJsonPath('counts.new', 1);
        $list->assertJsonPath('data.0.id', $message->id);

        $view = $this->postJson("/api/v1/admin/contact-messages/{$message->id}/view", []);
        $view->assertOk();
        $view->assertJsonPath('data.status', ContactMessage::STATUS_READ);

        $update = $this->postJson("/api/v1/admin/contact-messages/{$message->id}/update", [
            'status' => ContactMessage::STATUS_READ,
            'admin_notes' => 'Followed up by email.',
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.status', ContactMessage::STATUS_READ);

        $archiveAttempt = $this->postJson("/api/v1/admin/contact-messages/{$message->id}/delete", []);
        $archiveAttempt->assertStatus(422);

        $this->postJson("/api/v1/admin/contact-messages/{$message->id}/update", [
            'status' => ContactMessage::STATUS_ARCHIVED,
        ])->assertOk();

        $delete = $this->postJson("/api/v1/admin/contact-messages/{$message->id}/delete", []);
        $delete->assertOk();
        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }
}
