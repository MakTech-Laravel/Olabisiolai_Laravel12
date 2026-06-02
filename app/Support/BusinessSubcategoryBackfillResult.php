<?php

namespace App\Support;

final class BusinessSubcategoryBackfillResult
{
    public function __construct(
        public readonly int $scanned = 0,
        public readonly int $updated = 0,
        public readonly int $skipped = 0,
    ) {}

    public function merge(self $other): self
    {
        return new self(
            scanned: $this->scanned + $other->scanned,
            updated: $this->updated + $other->updated,
            skipped: $this->skipped + $other->skipped,
        );
    }
}
