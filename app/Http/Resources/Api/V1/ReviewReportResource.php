<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
            'review' => $this->when(
                $this->relationLoaded('review') && $this->review !== null,
                fn () => [
                    'id' => $this->review->id,
                    'reviewer_name' => $this->review->is_anonymous ? 'Anonymous' : $this->review->full_name,
                    'rating' => $this->review->rating,
                    'review_text' => $this->review->review_text,
                    'is_approved' => $this->review->is_approved,
                    'business' => $this->when(
                        $this->review->relationLoaded('business') && $this->review->business !== null,
                        fn () => [
                            'id' => $this->review->business->id,
                            'business_name' => $this->review->business->business_name,
                        ]
                    ),
                ]
            ),
            'reporter' => $this->when(
                $this->relationLoaded('user') && $this->user !== null,
                fn () => [
                    'id' => $this->user->id,
                    'name' => trim($this->user->first_name.' '.$this->user->last_name),
                    'email' => $this->user->email,
                ]
            ),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }
}
