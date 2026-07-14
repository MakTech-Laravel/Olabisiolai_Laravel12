<?php

namespace App\Services;

use App\Models\SearchSynonym;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class SearchSynonymService
{
    private const CACHE_KEY = 'search_synonyms.map';

    private const CACHE_TTL_SECONDS = 3600;

    public function paginateSynonyms(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return SearchSynonym::query()
            ->when($search !== null && trim($search) !== '', function ($query) use ($search) {
                $keyword = trim($search);

                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('term', 'like', "%{$keyword}%")
                        ->orWhere('synonyms', 'like', "%{$keyword}%");
                });
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createSynonym(array $validated): SearchSynonym
    {
        $synonym = SearchSynonym::query()->create([
            'term' => $this->normalizeTerm($validated['term']),
            'synonyms' => $this->normalizeSynonyms($validated['synonyms']),
        ]);

        $this->forgetCache();

        return $synonym;
    }

    public function getSynonymById(int $synonymId): SearchSynonym
    {
        return SearchSynonym::query()->findOrFail($synonymId);
    }

    public function updateSynonym(SearchSynonym $synonym, array $validated): SearchSynonym
    {
        $synonym->update([
            'term' => $this->normalizeTerm($validated['term']),
            'synonyms' => $this->normalizeSynonyms($validated['synonyms']),
        ]);

        $this->forgetCache();

        return $synonym->fresh();
    }

    public function deleteSynonym(SearchSynonym $synonym): void
    {
        $synonym->delete();

        $this->forgetCache();
    }

    /**
     * Full term => synonyms map, cached until the next create/update/delete.
     * The table stays small (business-role and domain-term entries), so
     * loading it whole and caching it is cheaper than a query per token.
     *
     * @return array<string, list<string>>
     */
    public function getSynonymMap(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            return SearchSynonym::query()
                ->get(['term', 'synonyms'])
                ->mapWithKeys(fn (SearchSynonym $synonym) => [$synonym->term => $synonym->synonyms])
                ->all();
        });
    }

    private function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function normalizeTerm(string $term): string
    {
        return mb_strtolower(trim($term));
    }

    /**
     * @return list<string>
     */
    private function normalizeSynonyms(mixed $input): array
    {
        $parts = is_string($input) ? explode(',', $input) : (array) $input;

        return collect($parts)
            ->map(fn ($item) => mb_strtolower(trim((string) $item)))
            ->filter(fn (string $item) => $item !== '')
            ->unique()
            ->values()
            ->all();
    }
}
