<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->dropColumn([
                'original_price',
                'discount_label',
                'promotional_text',
                'promotion_starts_at',
                'promotion_ends_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->unsignedInteger('original_price')->nullable()->after('amount');
            $table->string('discount_label', 60)->nullable()->after('original_price');
            $table->string('promotional_text', 255)->nullable()->after('discount_label');
            $table->timestamp('promotion_starts_at')->nullable()->after('promotional_text');
            $table->timestamp('promotion_ends_at')->nullable()->after('promotion_starts_at');
        });
    }
};
