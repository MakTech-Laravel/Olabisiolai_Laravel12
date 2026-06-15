<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_info')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! $this->indexExists('business_info_user_id_unique')) {
            return;
        }

        $foreignKeys = DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            ['business_info', 'user_id'],
        );

        foreach ($foreignKeys as $foreignKey) {
            $name = (string) $foreignKey->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE `business_info` DROP FOREIGN KEY `{$name}`");
        }

        Schema::table('business_info', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        // No-op: handled by the original migration's down().
    }

    private function indexExists(string $indexName): bool
    {
        $rows = DB::select('SHOW INDEX FROM `business_info` WHERE Key_name = ?', [$indexName]);

        return count($rows) > 0;
    }
};
