<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewReplyResource extends JsonResource
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
            'review_id' => $this->review_id,
            'reply_text' => $this->reply_text,
            'vendor' => $this->when(
                $this->relationLoaded('vendor') && $this->vendor !== null,
                fn () => [
                    'id' => $this->vendor->id,
                    'first_name' => $this->vendor->first_name,
                    'last_name' => $this->vendor->last_name,
                    'email' => $this->vendor->email,
                    'full_name' => trim($this->vendor->first_name.' '.$this->vendor->last_name),
                ]
            ),
            'created_at' => $this->created_at->format('Y-m-d'),
            'updated_at' => $this->when(
                $this->updated_at && $this->updated_at->ne($this->created_at),
                fn () => [
                    'formatted' => $this->updated_at->format('Y-m-d'),
                    'human' => $this->updated_at->diffForHumans(),
                ]
            ),
        ];
    }
}
