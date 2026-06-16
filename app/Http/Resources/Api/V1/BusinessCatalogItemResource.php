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
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'price_kobo' => $this->price_kobo,
            'price_label' => $this->price_label,
            'price_from' => (bool) $this->price_from,
            'image_url' => public_media_url($this->image_path),
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
