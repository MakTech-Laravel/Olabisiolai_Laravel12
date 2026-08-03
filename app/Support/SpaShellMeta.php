<?php

namespace App\Support;

/**
 * Normalized SEO fields ready for SPA shell <head> injection.
 */
final class SpaShellMeta
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly ?string $keywords = null,
    ) {}
}
