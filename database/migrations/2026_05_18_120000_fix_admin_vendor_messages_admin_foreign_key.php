<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_vendor_messages')) {
            return;
        }

        Schema::table('admin_vendor_messages', function (Blueprint $table): void {
            $table->dropForeign(['admin_id']);
        });

        Schema::table('admin_vendor_messages', function (Blueprint $table): void {
            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_vendor_messages')) {
            return;
        }

        Schema::table('admin_vendor_messages', function (Blueprint $table): void {
            $table->dropForeign(['admin_id']);
        });

        Schema::table('admin_vendor_messages', function (Blueprint $table): void {
            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
