<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_info_id' => $this->business_info_id,
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
            'business' => $this->when(
                $this->relationLoaded('business') && $this->business !== null,
                fn() => [
                    'id' => $this->business->id,
                    'business_name' => $this->business->business_name,
                ]
            ),
            'reporter' => $this->when(
                $this->relationLoaded('user') && $this->user !== null,
                fn() => [
                    'id' => $this->user->id,
                    'name' => trim($this->user->first_name . ' ' . $this->user->last_name),
                    'email' => $this->user->email,
                ]
            ),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }
}
