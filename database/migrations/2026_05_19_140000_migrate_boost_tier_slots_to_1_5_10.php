<?php

use App\Models\BoostPurchaseRequest;
use App\Models\LgaBoost;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('boost_purchase_requests')
            ->where('tier_key', 'top_5')
            ->update(['tier_key' => 'top_10']);

        DB::table('boost_purchase_requests')
            ->where('tier_key', 'top_3')
            ->update(['tier_key' => 'top_5']);

        LgaBoost::query()->each(function (LgaBoost $lgaBoost): void {
            $tiers = $lgaBoost->tiers;
            if (! is_array($tiers) || $tiers === []) {
                return;
            }

            $migrated = [];
            foreach ($tiers as $tier) {
                if (! is_array($tier)) {
                    continue;
                }

                $key = (string) ($tier['key'] ?? '');
                $label = (string) ($tier['label'] ?? '');

                if ($key === 'top_5') {
                    $tier['key'] = 'top_10';
                    $tier['total_slots'] = 10;
                    $tier['label'] = $this->replaceTierLabel($label, 'Top 5', 'Top 10');
                } elseif ($key === 'top_3') {
                    $tier['key'] = 'top_5';
                    $tier['total_slots'] = 5;
                    $tier['label'] = $this->replaceTierLabel($label, 'Top 3', 'Top 5');
                } elseif ($key === 'top_10') {
                    $tier['total_slots'] = 10;
                } elseif ($key === 'top_1') {
                    $tier['total_slots'] = 1;
                }

                $migrated[] = $tier;
            }

            if ($migrated === []) {
                return;
            }

            $totalSlots = collect($migrated)->sum(fn (array $tier): int => (int) ($tier['total_slots'] ?? 0));
            $slotsSold = min((int) $lgaBoost->slots_sold, $totalSlots);

            $lgaBoost->update([
                'tiers' => $migrated,
                'total_slots' => $totalSlots,
                'slots_remaining' => max(0, $totalSlots - $slotsSold),
            ]);
        });

        BoostPurchaseRequest::query()
            ->where('tier_key', 'top_10')
            ->where('tier_label', 'like', '%Top 5%')
            ->get()
            ->each(function (BoostPurchaseRequest $request): void {
                $request->update([
                    'tier_label' => str_replace('Top 5', 'Top 10', (string) $request->tier_label),
                ]);
            });

        BoostPurchaseRequest::query()
            ->where('tier_key', 'top_5')
            ->where('tier_label', 'like', '%Top 3%')
            ->get()
            ->each(function (BoostPurchaseRequest $request): void {
                $request->update([
                    'tier_label' => str_replace('Top 3', 'Top 5', (string) $request->tier_label),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('boost_purchase_requests')
            ->where('tier_key', 'top_5')
            ->update(['tier_key' => 'top_3']);

        DB::table('boost_purchase_requests')
            ->where('tier_key', 'top_10')
            ->update(['tier_key' => 'top_5']);

        LgaBoost::query()->each(function (LgaBoost $lgaBoost): void {
            $tiers = $lgaBoost->tiers;
            if (! is_array($tiers) || $tiers === []) {
                return;
            }

            $migrated = [];
            foreach ($tiers as $tier) {
                if (! is_array($tier)) {
                    continue;
                }

                $key = (string) ($tier['key'] ?? '');
                $label = (string) ($tier['label'] ?? '');

                if ($key === 'top_10') {
                    $tier['key'] = 'top_5';
                    $tier['total_slots'] = 5;
                    $tier['label'] = $this->replaceTierLabel($label, 'Top 10', 'Top 5');
                } elseif ($key === 'top_5') {
                    $tier['key'] = 'top_3';
                    $tier['total_slots'] = 3;
                    $tier['label'] = $this->replaceTierLabel($label, 'Top 5', 'Top 3');
                }

                $migrated[] = $tier;
            }

            if ($migrated === []) {
                return;
            }

            $totalSlots = collect($migrated)->sum(fn (array $tier): int => (int) ($tier['total_slots'] ?? 0));
            $slotsSold = min((int) $lgaBoost->slots_sold, $totalSlots);

            $lgaBoost->update([
                'tiers' => $migrated,
                'total_slots' => $totalSlots,
                'slots_remaining' => max(0, $totalSlots - $slotsSold),
            ]);
        });
    }

    private function replaceTierLabel(string $label, string $from, string $to): string
    {
        if ($label === '') {
            return $to.' Boost';
        }

        return str_contains($label, $from) ? str_replace($from, $to, $label) : $label;
    }
};
