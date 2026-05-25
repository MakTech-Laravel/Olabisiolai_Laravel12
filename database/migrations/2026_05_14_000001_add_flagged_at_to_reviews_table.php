<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('flagged_at')->nullable()->after('flag_reason');
        });

        if (Schema::hasColumn('reviews', 'flagged_at')) {
            DB::table('reviews')
                ->where('is_approved', false)
                ->whereNull('flagged_at')
                ->update(['flagged_at' => DB::raw('updated_at')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('flagged_at');
        });
    }
};
