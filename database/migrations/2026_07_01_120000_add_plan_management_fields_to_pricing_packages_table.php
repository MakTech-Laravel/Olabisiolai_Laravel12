<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->string('billing_period', 20)->nullable()->after('type');
            $table->unsignedInteger('original_price')->nullable()->after('amount');
            $table->string('discount_label', 60)->nullable()->after('original_price');
            $table->string('promotional_text', 255)->nullable()->after('discount_label');
            $table->timestamp('promotion_starts_at')->nullable()->after('promotional_text');
            $table->timestamp('promotion_ends_at')->nullable()->after('promotion_starts_at');
            $table->boolean('is_recommended')->default(false)->after('is_active');
            $table->boolean('trial_eligible')->default(false)->after('is_recommended');
            $table->unsignedSmallInteger('trial_duration_days')->nullable()->after('trial_eligible');
            $table->string('badge', 40)->nullable()->after('trial_duration_days');
            $table->string('color', 30)->nullable()->after('badge');
            $table->string('icon', 40)->nullable()->after('color');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->dropColumn([
                'billing_period',
                'original_price',
                'discount_label',
                'promotional_text',
                'promotion_starts_at',
                'promotion_ends_at',
                'is_recommended',
                'trial_eligible',
                'trial_duration_days',
                'badge',
                'color',
                'icon',
                'deleted_at',
            ]);
        });
    }
};
