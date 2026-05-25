<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;

final class AttachmentTest extends MessagingTestCase
{
    public function test_user_can_upload_and_delete_attachment(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $user = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        Passport::actingAs($user, guard: 'api');

        $file = UploadedFile::fake()->create('doc.pdf', 120, 'application/pdf');

        $upload = $this->post('/api/v1/attachments', [
            'file' => $file,
        ]);

        $upload->assertCreated();
        $uuid = $upload->json('data.uuid');
        $this->assertNotEmpty($uuid);

        $this->deleteJson('/api/v1/attachments/'.$uuid)->assertOk();

        $this->assertDatabaseMissing('attachments', ['uuid' => $uuid]);
    }

    public function test_user_cannot_delete_foreign_attachment(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $owner = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $other = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        $attachment = Attachment::factory()->create([
            'uploader_id' => $owner->id,
            'file_path' => 'attachments/2026/05/test.pdf',
        ]);

        Storage::disk('local')->put($attachment->file_path, 'x');

        Passport::actingAs($other, guard: 'api');

        $this->deleteJson('/api/v1/attachments/'.$attachment->uuid)
            ->assertNotFound();
    }
}
