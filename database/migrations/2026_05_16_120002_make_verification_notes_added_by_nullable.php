<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('verification_notes')) {
            return;
        }

        Schema::table('verification_notes', function (Blueprint $table): void {
            $table->dropForeign(['added_by']);
        });

        Schema::table('verification_notes', function (Blueprint $table): void {
            $table->unsignedBigInteger('added_by')->nullable()->change();
            $table->foreign('added_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('verification_notes')) {
            return;
        }

        Schema::table('verification_notes', function (Blueprint $table): void {
            $table->dropForeign(['added_by']);
        });

        Schema::table('verification_notes', function (Blueprint $table): void {
            $table->unsignedBigInteger('added_by')->nullable(false)->change();
            $table->foreign('added_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
