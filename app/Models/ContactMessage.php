<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_READ = 'read';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'full_name',
        'email',
        'subject',
        'message',
        'status',
        'admin_notes',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public static function statusValues(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_READ,
            self::STATUS_ARCHIVED,
        ];
    }
}
