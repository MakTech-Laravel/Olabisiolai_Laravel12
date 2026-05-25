<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_info_id')->constrained('business_info')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('day');
            /** Stored as HH:MM (e.g. 09:11) — not a datetime/timestamp column. */
            $table->string('opening_time')->nullable();
            $table->string('closing_time')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['business_info_id', 'day']);
            $table->index('business_info_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
