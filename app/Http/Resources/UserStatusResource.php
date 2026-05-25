<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\UserStatus;
use App\Services\PresenceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserStatus
 */
final class UserStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = app(PresenceService::class)->toPublicPayload($this->resource);

        return $payload ?? [
            'status' => $this->status->value,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
