<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchSynonymResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'term' => $this->term,
            'synonyms' => is_array($this->synonyms) ? array_values($this->synonyms) : [],
            'created_at' => humanDateTime($this->created_at),
            'updated_at' => humanDateTime($this->updated_at),
        ];
    }
}
