<?php

namespace App\Models;

use App\Enums\CmsPageType;
use Database\Factories\CmsPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    /** @use HasFactory<CmsPageFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => CmsPageType::class,
        ];
    }
}
