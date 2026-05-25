<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_hours')) {
            return;
        }

        if (! Schema::hasColumn('business_hours', 'opening_time')) {
            return;
        }

        DB::statement('ALTER TABLE business_hours MODIFY opening_time VARCHAR(8) NULL');
        DB::statement('ALTER TABLE business_hours MODIFY closing_time VARCHAR(8) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_hours')) {
            return;
        }

        DB::statement('ALTER TABLE business_hours MODIFY opening_time TIMESTAMP NULL');
        DB::statement('ALTER TABLE business_hours MODIFY closing_time TIMESTAMP NULL');
    }
};
