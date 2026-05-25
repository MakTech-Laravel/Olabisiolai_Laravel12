<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lga_boosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')
                ->unique()
                ->constrained('locations')
                ->cascadeOnDelete();

            // ── General ──────────────────────────────────────────────────
            $table->boolean('enabled')->default(true);

            // ── Tiers (JSON) ─────────────────────────────────────────────
            // Example:
            // [
            //   { "key": "top_1",  "label": "Top-1",  "total_slots": 1,  "price_amount": 0 },
            //   { "key": "top_5",  "label": "Top-5",  "total_slots": 5,  "price_amount": 0 },
            //   { "key": "top_10", "label": "Top-10", "total_slots": 10, "price_amount": 0 }
            // ]
            $table->json('tiers');

            // ── Durations (JSON) ─────────────────────────────────────────
            // Example:
            // [
            //   { "days": 7,  "enabled": true, "price_amount": 0 },
            //   { "days": 14, "enabled": true, "price_amount": 0 },
            //   { "days": 30, "enabled": true, "price_amount": 0 }
            // ]
            $table->json('durations');

            // ── Aggregate stats ──────────────────────────────────────────
            $table->unsignedInteger('total_slots')->default(0);
            $table->unsignedInteger('slots_sold')->default(0);
            $table->unsignedInteger('slots_remaining')->default(0);
            $table->unsignedInteger('active_boosts')->default(0);
            $table->unsignedInteger('expired_boosts')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lga_boosts');
    }
};
