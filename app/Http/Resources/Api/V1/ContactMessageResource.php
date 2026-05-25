<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status,
            'admin_notes' => $this->admin_notes,
            'read_at' => $this->read_at ? humanDateTime($this->read_at) : null,
            'read_at_iso' => $this->read_at?->toIso8601String(),
            'created_at' => humanDateTime($this->created_at),
            'created_at_iso' => $this->created_at?->toIso8601String(),
            'updated_at' => humanDateTime($this->updated_at),
        ];
    }
}
