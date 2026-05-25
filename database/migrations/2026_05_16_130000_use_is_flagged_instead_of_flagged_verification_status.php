<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_info')) {
            return;
        }

        DB::table('business_info')
            ->whereIn('verification_status', ['flagged', 'rejected'])
            ->update([
                'verification_status' => 'none',
                'is_flagged' => true,
            ]);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE business_info MODIFY verification_status ENUM('none', 'pending', 'approved') NOT NULL DEFAULT 'none'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_info') || Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE business_info MODIFY verification_status ENUM('none', 'pending', 'approved', 'flagged') NOT NULL DEFAULT 'none'");
    }
};
