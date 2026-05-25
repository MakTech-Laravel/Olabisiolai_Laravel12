<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AttachmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_stores_file_and_creates_record(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $user = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

        $file = UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf');

        $attachment = app(AttachmentService::class)->upload($file, $user);

        Storage::disk('local')->assertExists($attachment->file_path);
        $this->assertDatabaseHas('attachments', ['uuid' => $attachment->uuid]);
    }
}
