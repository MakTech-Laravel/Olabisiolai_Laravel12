<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_info', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->change();
            $table->unsignedBigInteger('category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('business_info', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable(false)->change();
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
        });
    }
};
