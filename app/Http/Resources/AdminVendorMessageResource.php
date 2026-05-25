<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AdminVendorMessage */
final class AdminVendorMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_info_id' => $this->business_info_id,
            'admin_id' => $this->admin_id,
            'vendor_id' => $this->vendor_id,
            'message' => $this->message,
            'created_at' => $this->created_at?->toIso8601String(),
            'admin' => $this->whenLoaded('admin', fn () => [
                'id' => $this->admin?->id,
                'name' => $this->admin?->name,
                'email' => $this->admin?->email,
            ]),
            'vendor' => $this->whenLoaded('vendor', fn () => [
                'id' => $this->vendor?->id,
                'name' => $this->vendor?->name,
                'email' => $this->vendor?->email,
            ]),
        ];
    }
}
