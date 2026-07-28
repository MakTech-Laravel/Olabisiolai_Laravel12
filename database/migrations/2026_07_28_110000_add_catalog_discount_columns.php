<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_catalog_items', function (Blueprint $table): void {
            $table->unsignedInteger('original_price_kobo')->nullable()->after('price_kobo');
            $table->string('discount_type', 16)->nullable()->after('price_from');
            $table->unsignedInteger('discount_value')->nullable()->after('discount_type');
            $table->boolean('has_discount')->default(false)->after('discount_value');
        });

        Schema::table('buyer_cart_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('original_unit_price_kobo')->nullable()->after('unit_price_kobo');
        });
    }

    public function down(): void
    {
        Schema::table('business_catalog_items', function (Blueprint $table): void {
            $table->dropColumn([
                'original_price_kobo',
                'discount_type',
                'discount_value',
                'has_discount',
            ]);
        });

        Schema::table('buyer_cart_items', function (Blueprint $table): void {
            $table->dropColumn('original_unit_price_kobo');
        });
    }
};
