<?php

namespace App\Support;

class ParsedPublicSearchQuery
{
    /**
     * @param  list<int>  $locationIds
     * @param  list<list<string>>  $serviceTermGroups
     */
    public function __construct(
        public readonly string $originalQuery,
        public readonly array $locationIds = [],
        public readonly array $serviceTermGroups = [],
        public readonly bool $resolvedLocationFromSearch = false,
    ) {}

    public function hasLocationOnly(): bool
    {
        return $this->locationIds !== [] && $this->serviceTermGroups === [];
    }

    public function hasParsedIntent(): bool
    {
        return $this->locationIds !== [] || $this->serviceTermGroups !== [];
    }
}
