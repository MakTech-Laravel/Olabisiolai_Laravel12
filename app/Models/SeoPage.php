<?php

namespace App\Models;

use Database\Factories\SeoPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoPage extends Model
{
    /** @use HasFactory<SeoPageFactory> */
    use HasFactory;

    protected $fillable = [
        'path',
        'page_name',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'changefreq',
        'priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'float',
        ];
    }

    /**
     * Normalize SPA paths: empty → `/`, ensure leading slash, strip trailing slash (except root).
     */
    public static function normalizePath(?string $path): string
    {
        $trimmed = trim((string) $path);
        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }

        $withSlash = '/'.ltrim($trimmed, '/');

        return rtrim($withSlash, '/') ?: '/';
    }
}
