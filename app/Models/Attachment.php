<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttachmentType;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property-read int $id
 * @property-read string $uuid
 */
final class Attachment extends Model
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    protected $table = 'attachments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'message_id',
        'uploader_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'type',
        'thumbnail_path',
        'metadata',
    ];

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (Attachment $attachment): void {
            if ($attachment->uuid === null || $attachment->uuid === '') {
                $attachment->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttachmentType::class,
            'metadata' => 'array',
            'file_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}
