<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Location;
use App\Support\ParsedPublicSearchQuery;

class PublicSearchQueryParser
{
    /** @var list<array{id: int, names: list<string>}>|null */
    private ?array $locationCandidates = null;

    /** @var list<array{id: int, name: string, subcategories: list<string>}>|null */
    private ?array $categoryCandidates = null;

    /** @var array<string, list<string>>|null */
    private ?array $synonymMap = null;

    public function __construct(private readonly SearchSynonymService $searchSynonymService) {}

    /**
     * @var list<string>
     */
    private const STOP_WORDS = [
        'in',
        'at',
        'near',
        'around',
        'for',
        'the',
        'a',
        'an',
        'and',
        'or',
        'of',
        'by',
    ];

    public function parse(string $rawQuery): ParsedPublicSearchQuery
    {
        $original = trim($rawQuery);
        if ($original === '') {
            return new ParsedPublicSearchQuery($original);
        }

        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', $original) ?? $original);

        [$locationIds, $remaining] = $this->extractLocationIds($normalized);
        $serviceTermGroups = $this->buildServiceTermGroups($remaining);

        return new ParsedPublicSearchQuery(
            originalQuery: $original,
            locationIds: $locationIds,
            serviceTermGroups: $serviceTermGroups,
            resolvedLocationFromSearch: $locationIds !== [],
        );
    }

    /**
     * @return list<string>
     */
    public function extractKeywords(string $rawQuery): array
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($rawQuery)) ?? trim($rawQuery));
        if ($normalized === '') {
            return [];
        }

        return $this->tokenizeServiceTerms($normalized);
    }

    /**
     * @return list<string>
     */
    public function expandTerms(string $token): array
    {
        return $this->expandToken($token);
    }

    /**
     * @return array{0: list<int>, 1: string}
     */
    private function extractLocationIds(string $normalized): array
    {
        $remaining = $normalized;
        $locationIds = [];

        foreach ($this->locationCandidatesSorted() as $candidate) {
            foreach ($candidate['names'] as $name) {
                if ($name === '' || ! $this->containsWholePhrase($remaining, $name)) {
                    continue;
                }

                $locationIds[] = $candidate['id'];
                $remaining = $this->stripLocationPhrase($remaining, $name);
                break;
            }
        }

        return [array_values(array_unique($locationIds)), trim(preg_replace('/\s+/u', ' ', $remaining) ?? $remaining)];
    }

    /**
     * @return list<string>
     */
    private function tokenizeServiceTerms(string $remaining): array
    {
        if ($remaining === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $remaining, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $tokens,
            static fn (string $token): bool => $token !== '' && ! in_array($token, self::STOP_WORDS, true),
        ));
    }

    /**
     * @return list<list<string>>
     */
    private function buildServiceTermGroups(string $remaining): array
    {
        $groups = [];
        $remaining = trim(preg_replace('/\s+/u', ' ', $remaining) ?? $remaining);

        foreach ($this->knownServicePhrasesSorted() as $phrase) {
            if (! $this->containsWholePhrase($remaining, $phrase)) {
                continue;
            }

            $group = $this->expandToken($phrase);
            if ($group !== []) {
                $groups[] = $group;
            }

            $remaining = $this->stripLocationPhrase($remaining, $phrase);
        }

        foreach ($this->tokenizeServiceTerms($remaining) as $token) {
            $group = $this->expandToken($token);
            if ($group !== []) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function knownServicePhrasesSorted(): array
    {
        $phrases = [];

        foreach ($this->categoryCandidates() as $category) {
            if ($category['name'] !== '') {
                $phrases[] = $category['name'];
            }

            foreach ($category['subcategories'] as $subcategory) {
                $phrases[] = $subcategory;
            }
        }

        $phrases = array_values(array_unique(array_filter($phrases, static fn (string $phrase): bool => $phrase !== '')));

        usort($phrases, static fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));

        return $phrases;
    }

    /**
     * @return list<string>
     */
    private function expandToken(string $token): array
    {
        $token = mb_strtolower(trim($token));
        if ($token === '') {
            return [];
        }

        $terms = [$token];

        $singular = $this->singularize($token);
        if ($singular !== $token) {
            $terms[] = $singular;
        }

        if (! str_ends_with($token, 's')) {
            $terms[] = $token.'s';
        }

        foreach (array_unique([$token, $singular]) as $lookupToken) {
            $synonyms = $this->synonymMap()[$lookupToken] ?? [];
            foreach ($synonyms as $synonym) {
                $synonym = mb_strtolower(trim((string) $synonym));
                if ($synonym !== '') {
                    $terms[] = $synonym;
                }
            }
        }

        foreach ($this->categoryCandidates() as $category) {
            $categoryName = mb_strtolower($category['name']);
            if ($this->termsOverlap($token, $categoryName)) {
                $terms[] = $categoryName;
            }

            foreach ($category['subcategories'] as $subcategory) {
                if ($this->termsOverlap($token, $subcategory)) {
                    $terms[] = $subcategory;
                }
            }
        }

        return array_values(array_unique(array_filter($terms, static fn (string $term): bool => $term !== '')));
    }

    private function termsOverlap(string $token, string $candidate): bool
    {
        if ($token === '' || $candidate === '') {
            return false;
        }

        if (str_contains($candidate, $token) || str_contains($token, $candidate)) {
            return true;
        }

        foreach (preg_split('/\s+/u', $candidate, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $candidateWord) {
            if ($this->fuzzyWordsMatch($token, $candidateWord)) {
                return true;
            }
        }

        return $this->fuzzyWordsMatch($token, $candidate);
    }

    /**
     * Typo-tolerant word comparison: normalizes plurals, then allows a small
     * edit-distance gap that scales with word length (catches near-miss typos
     * without matching unrelated short words).
     */
    private function fuzzyWordsMatch(string $a, string $b): bool
    {
        $a = $this->singularize($a);
        $b = $this->singularize($b);

        if ($a === $b) {
            return true;
        }

        if (str_contains($a, $b) || str_contains($b, $a)) {
            return true;
        }

        $maxLength = max(mb_strlen($a), mb_strlen($b));
        if ($maxLength < 4) {
            return false;
        }

        $threshold = match (true) {
            $maxLength <= 5 => 1,
            $maxLength <= 8 => 2,
            default => 3,
        };

        return levenshtein($a, $b) <= $threshold;
    }

    private function singularize(string $word): string
    {
        $length = mb_strlen($word);

        if ($length > 4 && str_ends_with($word, 'ies')) {
            return mb_substr($word, 0, -3).'y';
        }

        if ($length > 4 && (str_ends_with($word, 'ses') || str_ends_with($word, 'xes') || str_ends_with($word, 'ches') || str_ends_with($word, 'shes'))) {
            return mb_substr($word, 0, -2);
        }

        if ($length > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            return mb_substr($word, 0, -1);
        }

        return $word;
    }

    private function containsWholePhrase(string $haystack, string $phrase): bool
    {
        $pattern = '/(?<!\p{L})'.preg_quote($phrase, '/').'(?!\p{L})/u';

        return preg_match($pattern, $haystack) === 1;
    }

    private function stripLocationPhrase(string $haystack, string $phrase): string
    {
        $quoted = preg_quote($phrase, '/');
        $haystack = preg_replace('/(?<!\p{L})(?:in|at|near|around)\s+'.$quoted.'(?!\p{L})/u', ' ', $haystack) ?? $haystack;
        $haystack = preg_replace('/(?<!\p{L})'.$quoted.'(?!\p{L})/u', ' ', $haystack) ?? $haystack;

        return trim(preg_replace('/\s+/u', ' ', $haystack) ?? $haystack);
    }

    /**
     * @return list<array{id: int, names: list<string>}>
     */
    private function locationCandidatesSorted(): array
    {
        $candidates = $this->locationCandidates();

        usort(
            $candidates,
            static function (array $left, array $right): int {
                $leftMax = max(array_map(static fn (string $name): int => mb_strlen($name), $left['names']));
                $rightMax = max(array_map(static fn (string $name): int => mb_strlen($name), $right['names']));

                return $rightMax <=> $leftMax;
            },
        );

        return $candidates;
    }

    /**
     * @return list<array{id: int, names: list<string>}>
     */
    private function locationCandidates(): array
    {
        if ($this->locationCandidates !== null) {
            return $this->locationCandidates;
        }

        $this->locationCandidates = Location::query()
            ->select(['id', 'lga_name', 'city_name', 'state_name'])
            ->get()
            ->map(static function (Location $location): array {
                $names = array_values(array_unique(array_filter([
                    mb_strtolower(trim((string) $location->lga_name)),
                    mb_strtolower(trim((string) $location->city_name)),
                    mb_strtolower(trim((string) $location->state_name)),
                ], static fn (string $name): bool => $name !== '')));

                return [
                    'id' => (int) $location->id,
                    'names' => $names,
                ];
            })
            ->filter(static fn (array $candidate): bool => $candidate['names'] !== [])
            ->values()
            ->all();

        return $this->locationCandidates;
    }

    /**
     * @return array<string, list<string>>
     */
    private function synonymMap(): array
    {
        if ($this->synonymMap !== null) {
            return $this->synonymMap;
        }

        return $this->synonymMap = $this->searchSynonymService->getSynonymMap();
    }

    /**
     * @return list<array{id: int, name: string, subcategories: list<string>}>
     */
    private function categoryCandidates(): array
    {
        if ($this->categoryCandidates !== null) {
            return $this->categoryCandidates;
        }

        $this->categoryCandidates = Category::query()
            ->select(['id', 'name', 'subcategories'])
            ->get()
            ->map(static function (Category $category): array {
                $subcategories = is_array($category->subcategories) ? $category->subcategories : [];
                $normalizedSubcategories = [];

                foreach ($subcategories as $subcategory) {
                    if (! is_string($subcategory)) {
                        continue;
                    }

                    $normalized = mb_strtolower(trim($subcategory));
                    if ($normalized !== '') {
                        $normalizedSubcategories[] = $normalized;
                    }
                }

                return [
                    'id' => (int) $category->id,
                    'name' => mb_strtolower(trim((string) $category->name)),
                    'subcategories' => $normalizedSubcategories,
                ];
            })
            ->filter(static fn (array $category): bool => $category['name'] !== '')
            ->values()
            ->all();

        return $this->categoryCandidates;
    }
}
