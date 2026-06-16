<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_info_id')->constrained('business_info')->cascadeOnDelete();
            $table->string('type', 16)->default('service');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_kobo')->nullable();
            $table->string('price_label', 64)->nullable();
            $table->boolean('price_from')->default(false);
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['business_info_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_catalog_items');
    }
};
