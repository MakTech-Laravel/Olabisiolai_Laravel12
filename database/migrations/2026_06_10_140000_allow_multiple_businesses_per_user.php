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

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $this->dropMysqlForeignKeysOnUserId();
        } else {
            Schema::table('business_info', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        Schema::table('business_info', function (Blueprint $table) {
            if ($this->indexExists('business_info', 'business_info_user_id_unique')) {
                $table->dropUnique(['user_id']);
            }

            if (! $this->indexExists('business_info', 'business_info_user_id_index')) {
                $table->index('user_id');
            }

            if (! $this->foreignKeyExistsOnUserId()) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_info')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $this->dropMysqlForeignKeysOnUserId();
        } else {
            Schema::table('business_info', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        Schema::table('business_info', function (Blueprint $table) {
            if ($this->indexExists('business_info', 'business_info_user_id_index')) {
                $table->dropIndex(['user_id']);
            }

            if (! $this->indexExists('business_info', 'business_info_user_id_unique')) {
                $table->unique('user_id');
            }

            if (! $this->foreignKeyExistsOnUserId()) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            }
        });
    }

    private function dropMysqlForeignKeysOnUserId(): void
    {
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
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

            return count($rows) > 0;
        }

        return false;
    }

    private function foreignKeyExistsOnUserId(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            return false;
        }

        $rows = DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            ['business_info', 'user_id'],
        );

        return count($rows) > 0;
    }
};
