<?php

namespace App\Support;

/**
 * Fully resolved SEO payload for resolve API + spa-shell injection.
 */
final class ResolvedSeo
{
    /**
     * @param  array<string, mixed>  $og
     * @param  array<string, mixed>  $twitter
     * @param  list<array<string, mixed>>  $jsonLd
     */
    public function __construct(
        public readonly ?string $matchedEntity,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $keywords,
        public readonly string $canonical,
        public readonly string $robots,
        public readonly array $og,
        public readonly array $twitter,
        public readonly array $jsonLd,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'matched_entity' => $this->matchedEntity,
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'canonical' => $this->canonical,
            'robots' => $this->robots,
            'og' => $this->og,
            'twitter' => $this->twitter,
            'json_ld' => $this->jsonLd,
        ];
    }

    public function hasMatch(): bool
    {
        return $this->matchedEntity !== null;
    }
}
