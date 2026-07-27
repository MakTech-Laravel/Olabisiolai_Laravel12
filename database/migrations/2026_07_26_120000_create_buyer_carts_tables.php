<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('business_info_id')->constrained('business_info')->cascadeOnDelete();
            $table->string('status', 16)->default('open'); // open | sent
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'business_info_id', 'status']);
        });

        Schema::create('buyer_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_cart_id')->constrained('buyer_carts')->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained('business_catalog_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('name');
            $table->unsignedBigInteger('unit_price_kobo')->nullable();
            $table->string('price_display');
            $table->boolean('price_from')->default(false);
            $table->string('image_url', 2048)->nullable();
            $table->timestamps();

            $table->unique(['buyer_cart_id', 'catalog_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_cart_items');
        Schema::dropIfExists('buyer_carts');
    }
};
