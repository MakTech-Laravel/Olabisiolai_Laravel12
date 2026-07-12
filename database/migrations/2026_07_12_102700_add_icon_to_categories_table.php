<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('categories', 'icon')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('subcategories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('categories', 'icon')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
