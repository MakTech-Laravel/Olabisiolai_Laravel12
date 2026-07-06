<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table) {
            $table->foreignId('pricing_package_id')->nullable()->after('business_info_id')
                ->constrained('pricing_packages')->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_package_id');
            $table->dropColumn('trial_ends_at');
        });
    }
};
