<?php

namespace App\Services;

use App\Models\SeoPage;
use App\Support\ResolvedSeo;
use Illuminate\Support\Facades\Cache;

class SpaShellService
{
    public function __construct(
        private readonly SeoPageService $seoPages,
        private readonly SeoResolverService $resolver,
        private readonly SpaShellTemplateService $templates,
    ) {}

    /**
     * Return the SPA index.html with per-path SEO meta injected when a match exists.
     */
    public function render(?string $path): string
    {
        $normalized = SeoPage::normalizePath($path);
        $ttl = max(60, (int) config('seo.spa_shell_cache_ttl', 86400));
        $cacheKey = $this->seoPages->shellCacheKey($normalized);

        return Cache::remember($cacheKey, $ttl, function () use ($normalized): string {
            $html = $this->templates->load();
            $seo = $this->resolver->resolve($normalized);

            if (! $seo->hasMatch()) {
                return $html;
            }

            return $this->inject($html, $seo);
        });
    }

    private function inject(string $html, ResolvedSeo $seo): string
    {
        $title = $seo->title !== '' ? $seo->title : 'Gidira';

        $html = preg_replace(
            '/<title>[^<]*<\/title>/i',
            '<title>'.$this->escape($title).'</title>',
            $html,
            1
        ) ?? $html;

        $tags = [];
        if ($seo->description !== null && $seo->description !== '') {
            $tags[] = '<meta name="description" content="'.$this->escape($seo->description).'" />';
        }
        if ($seo->keywords !== null && $seo->keywords !== '') {
            $tags[] = '<meta name="keywords" content="'.$this->escape($seo->keywords).'" />';
        }
        $tags[] = '<meta name="robots" content="'.$this->escape($seo->robots).'" />';

        foreach ($seo->og as $property => $content) {
            if (! is_string($content) || $content === '') {
                continue;
            }
            $tags[] = '<meta property="og:'.$this->escape((string) $property).'" content="'.$this->escape($content).'" />';
        }

        foreach ($seo->twitter as $name => $content) {
            if (! is_string($content) || $content === '') {
                continue;
            }
            $tags[] = '<meta name="twitter:'.$this->escape((string) $name).'" content="'.$this->escape($content).'" />';
        }

        $tags[] = '<link rel="canonical" href="'.$this->escape($seo->canonical).'" />';

        foreach ($seo->jsonLd as $block) {
            $json = json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (! is_string($json)) {
                continue;
            }
            $tags[] = '<script type="application/ld+json">'.$json.'</script>';
        }

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
