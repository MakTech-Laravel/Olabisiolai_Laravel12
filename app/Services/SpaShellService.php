<?php

namespace App\Services;

use App\Models\SeoPage;
use App\Support\FrontendUrl;
use App\Support\SpaShellMeta;
use Illuminate\Support\Facades\Cache;

class SpaShellService
{
    public function __construct(
        private readonly SeoPageService $seoPages,
        private readonly SpaShellMetaResolver $metaResolver,
        private readonly SpaShellTemplateService $templates,
    ) {}

    /**
     * Return the SPA index.html with per-path SEO meta injected when available.
     */
    public function render(?string $path): string
    {
        $normalized = SeoPage::normalizePath($path);
        $ttl = max(60, (int) config('seo.spa_shell_cache_ttl', 86400));
        $cacheKey = $this->seoPages->shellCacheKey($normalized);

        return Cache::remember($cacheKey, $ttl, function () use ($normalized): string {
            $html = $this->templates->load();
            $meta = $this->metaResolver->resolve($normalized);

            if ($meta === null) {
                return $html;
            }

            return $this->injectMeta($html, $meta, $normalized);
        });
    }

    private function injectMeta(string $html, SpaShellMeta $meta, string $normalizedPath): string
    {
        $title = $meta->title !== '' ? $meta->title : 'Gidira';
        $description = $meta->description;
        $keywords = $meta->keywords;
        $canonical = FrontendUrl::to($normalizedPath);

        $html = preg_replace(
            '/<title>[^<]*<\/title>/i',
            '<title>'.$this->escape($title).'</title>',
            $html,
            1
        ) ?? $html;

        $tags = [];
        if ($description !== null && $description !== '') {
            $tags[] = '<meta name="description" content="'.$this->escape($description).'" />';
        }
        if ($keywords !== null && $keywords !== '') {
            $tags[] = '<meta name="keywords" content="'.$this->escape($keywords).'" />';
        }
        $tags[] = '<meta property="og:type" content="website" />';
        $tags[] = '<meta property="og:site_name" content="Gidira" />';
        $tags[] = '<meta property="og:title" content="'.$this->escape($title).'" />';
        if ($description !== null && $description !== '') {
            $tags[] = '<meta property="og:description" content="'.$this->escape($description).'" />';
        }
        $tags[] = '<meta property="og:url" content="'.$this->escape($canonical).'" />';
        $tags[] = '<meta name="twitter:card" content="summary" />';
        $tags[] = '<meta name="twitter:title" content="'.$this->escape($title).'" />';
        if ($description !== null && $description !== '') {
            $tags[] = '<meta name="twitter:description" content="'.$this->escape($description).'" />';
        }
        $tags[] = '<link rel="canonical" href="'.$this->escape($canonical).'" />';

        $injection = "\n    ".implode("\n    ", $tags)."\n";

        $html = preg_replace('/\s*<!--gidira-seo-start-->.*?<!--gidira-seo-end-->\s*/s', "\n", $html) ?? $html;

        $block = "<!--gidira-seo-start-->{$injection}<!--gidira-seo-end-->";

        if (stripos($html, '</head>') !== false) {
            return preg_replace('/<\/head>/i', $block.'</head>', $html, 1) ?? ($html.$block);
        }

        return $html.$block;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
