<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();

            // ── Country ─────────────────────────────────────────────────
            $table->string('country_name')->default('Nigeria');
            $table->string('country_iso_code')->default('NG');
            $table->boolean('country_is_active')->default(true);
            $table->unsignedSmallInteger('country_sort_order')->default(0);

            // ── State ────────────────────────────────────────────────────
            $table->string('state_name');
            $table->string('state_slug')->nullable();

            // ── City ─────────────────────────────────────────────────────
            $table->string('city_name')->nullable();
            $table->string('city_slug')->nullable();

            // ── LGA ──────────────────────────────────────────────────────
            $table->string('lga_name');
            $table->string('lga_slug')->nullable()->unique();
            $table->unsignedInteger('vendor_count')->default(0);

            // ── Google Maps / Geocoding ──────────────────────────────────
            $table->string('google_place_id')->nullable()->index();
            $table->string('google_resource_name')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->text('formatted_address')->nullable();

            // ── Viewport bounds ──────────────────────────────────────────
            $table->decimal('viewport_north', 10, 7)->nullable();
            $table->decimal('viewport_south', 10, 7)->nullable();
            $table->decimal('viewport_east', 10, 7)->nullable();
            $table->decimal('viewport_west', 10, 7)->nullable();

            // ── Raw Google address_components ────────────────────────────
            $table->json('address_components_json')->nullable();

            $table->timestamps();

            $table->index(['state_name', 'lga_name']);
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
