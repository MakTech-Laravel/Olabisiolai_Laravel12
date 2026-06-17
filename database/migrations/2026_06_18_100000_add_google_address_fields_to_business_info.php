<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_info')) {
            return;
        }

        Schema::table('business_info', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_info', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('street_address');
            }
            if (! Schema::hasColumn('business_info', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('business_info', 'google_place_id')) {
                $table->string('google_place_id')->nullable()->after('longitude');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_info')) {
            return;
        }

        Schema::table('business_info', function (Blueprint $table): void {
            if (Schema::hasColumn('business_info', 'google_place_id')) {
                $table->dropColumn('google_place_id');
            }
            if (Schema::hasColumn('business_info', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('business_info', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};
