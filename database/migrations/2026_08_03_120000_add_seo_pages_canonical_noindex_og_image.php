<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_pages', function (Blueprint $table) {
            $table->string('canonical_url')->nullable()->after('meta_keywords');
            $table->boolean('noindex')->default(false)->after('canonical_url');
            $table->string('og_image')->nullable()->after('noindex');
        });
    }

    public function down(): void
    {
        Schema::table('seo_pages', function (Blueprint $table) {
            $table->dropColumn(['canonical_url', 'noindex', 'og_image']);
        });
    }
};
