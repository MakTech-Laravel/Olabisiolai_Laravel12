<?php

namespace Database\Seeders;

use App\Models\SeoPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SeoPageSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $pages = collect(config('sitemap-urls', []))
            ->map(function (array $entry) use ($now): ?array {
                if (! array_key_exists('path', $entry) || $entry['path'] === null || $entry['path'] === '') {
                    return null;
                }

                $path = SeoPage::normalizePath((string) $entry['path']);

                return [
                    'path' => $path,
                    'page_name' => $this->pageNameFromPath($path),
                    'changefreq' => $entry['changefreq'] ?? null,
                    'priority' => isset($entry['priority']) ? (float) $entry['priority'] : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($pages === []) {
            return;
        }

        // Preserve existing meta_* on re-seed (upsert only structural fields).
        SeoPage::upsert(
            $pages,
            ['path'],
            ['page_name', 'changefreq', 'priority', 'updated_at']
        );
    }

    private function pageNameFromPath(string $path): string
    {
        if ($path === '/') {
            return 'Home';
        }

        $slug = ltrim($path, '/');

        return Str::of($slug)
            ->replace(['-', '/'], ' ')
            ->title()
            ->toString();
    }
}
