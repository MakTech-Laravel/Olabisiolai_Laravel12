<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttachmentType;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'message_id' => null,
            'uploader_id' => User::factory(),
            'file_name' => 'file.pdf',
            'file_path' => 'attachments/2026/05/'.fake()->uuid().'.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'type' => AttachmentType::Document,
        ];
    }
}
