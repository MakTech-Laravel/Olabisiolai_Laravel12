<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boost_purchase_requests', function (Blueprint $table): void {
            $table->boolean('is_flagged')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('boost_purchase_requests', function (Blueprint $table): void {
            $table->dropColumn('is_flagged');
        });
    }
};
