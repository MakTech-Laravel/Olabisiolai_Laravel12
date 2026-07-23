<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subcategories = is_array($this->subcategories) ? array_values($this->subcategories) : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'subcategories' => $subcategories,
            'subcategories_count' => count($subcategories),
            'business_count' => (int) ($this->business_infos_count ?? 0),
            'icon' => $this->icon,
            'icon_url' => $this->icon_url,
            'created_at' => humanDateTime($this->created_at),
            'updated_at' => humanDateTime($this->updated_at),
        ];
    }
}
