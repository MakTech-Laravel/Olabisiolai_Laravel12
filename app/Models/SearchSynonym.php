<?php

namespace App\Models;

use Database\Factories\SearchSynonymFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchSynonym extends Model
{
    /** @use HasFactory<SearchSynonymFactory> */
    use HasFactory;

    protected $fillable = [
        'term',
        'synonyms',
    ];

    protected function casts(): array
    {
        return [
            'synonyms' => 'array',
        ];
    }
}
