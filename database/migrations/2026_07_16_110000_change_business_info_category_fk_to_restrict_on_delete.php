<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stop category deletion from cascading to businesses.
 * Deletes are blocked while businesses still reference the category;
 * admins must reassign them first via the API.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Orphaned category_id values block recreating the FK.
        DB::table('business_info')
            ->whereNotNull('category_id')
            ->whereNotIn('category_id', DB::table('categories')->select('id'))
            ->update(['category_id' => null]);

        $this->dropCategoryForeignKeyIfExists();

        Schema::table('business_info', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $this->dropCategoryForeignKeyIfExists();

        Schema::table('business_info', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    private function dropCategoryForeignKeyIfExists(): void
    {
        $database = Schema::getConnection()->getDatabaseName();

        $constraint = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', 'business_info')
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->where('CONSTRAINT_NAME', 'business_info_category_id_foreign')
            ->exists();

        if (! $constraint) {
            return;
        }

        Schema::table('business_info', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });
    }
};
