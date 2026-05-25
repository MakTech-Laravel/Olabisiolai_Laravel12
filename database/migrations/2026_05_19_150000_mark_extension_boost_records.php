<?php

use App\Enums\BoostPurchaseRequestStatus;
use App\Models\BoostPurchaseRequest;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        BoostPurchaseRequest::query()
            ->where('status', BoostPurchaseRequestStatus::Approved)
            ->whereNotNull('metadata->source_campaign_id')
            ->where('metadata->renew_type', 'extend')
            ->orderBy('id')
            ->each(function (BoostPurchaseRequest $request): void {
                $parentId = (int) ($request->metadata['source_campaign_id'] ?? 0);
                if ($parentId <= 0) {
                    return;
                }

                $parent = BoostPurchaseRequest::query()->find($parentId);
                if ($parent === null) {
                    return;
                }

                $metadata = $request->metadata ?? [];
                if ((bool) ($metadata['is_extension_record'] ?? false)) {
                    return;
                }

                $metadata['is_extension_record'] = true;
                $metadata['extension_parent_id'] = $parentId;

                $request->update([
                    'metadata' => $metadata,
                    'starts_at' => $parent->starts_at,
                    'ends_at' => $parent->ends_at,
                ]);
            });
    }

    public function down(): void
    {
        BoostPurchaseRequest::query()
            ->where('metadata->is_extension_record', true)
            ->get()
            ->each(function (BoostPurchaseRequest $request): void {
                $metadata = $request->metadata ?? [];
                unset($metadata['is_extension_record'], $metadata['extension_parent_id']);
                $request->update(['metadata' => $metadata]);
            });
    }
};
