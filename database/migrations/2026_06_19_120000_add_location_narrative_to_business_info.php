<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_info', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_info', 'location_narrative')) {
                $table->string('location_narrative', 255)->nullable()->after('street_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_info', function (Blueprint $table): void {
            if (Schema::hasColumn('business_info', 'location_narrative')) {
                $table->dropColumn('location_narrative');
            }
        });
    }
};
