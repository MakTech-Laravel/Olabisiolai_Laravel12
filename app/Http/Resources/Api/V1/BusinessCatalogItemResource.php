<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessCatalogItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paths = $this->resource->normalizedImagePaths();
        $urls = array_values(array_filter(
            array_map(static fn (string $path) => public_media_url($path, null), $paths),
        ));

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'price_kobo' => $this->price_kobo,
            'price_label' => $this->price_label,
            'price_from' => (bool) $this->price_from,
            'image_url' => $urls[0] ?? null,
            'image_urls' => $urls,
            'image_paths' => $paths,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
