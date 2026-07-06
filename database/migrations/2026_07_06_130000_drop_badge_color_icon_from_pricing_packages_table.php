<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->dropColumn(['badge', 'color', 'icon']);
        });
    }

    public function down(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->string('badge', 40)->nullable();
            $table->string('color', 30)->nullable();
            $table->string('icon', 40)->nullable();
        });
    }
};
