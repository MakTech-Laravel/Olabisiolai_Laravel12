<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_catalog_items', function (Blueprint $table): void {
            $table->json('image_paths')->nullable()->after('image_path');
        });

        DB::table('business_catalog_items')
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('business_catalog_items')
                        ->where('id', $row->id)
                        ->update([
                            'image_paths' => json_encode([(string) $row->image_path]),
                        ]);
                }
            });

        Schema::table('business_catalog_items', function (Blueprint $table): void {
            $table->dropColumn('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('business_catalog_items', function (Blueprint $table): void {
            $table->string('image_path')->nullable()->after('price_from');
        });

        DB::table('business_catalog_items')
            ->whereNotNull('image_paths')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $paths = json_decode((string) $row->image_paths, true);
                    $first = is_array($paths) && isset($paths[0]) && is_string($paths[0])
                        ? $paths[0]
                        : null;

                    DB::table('business_catalog_items')
                        ->where('id', $row->id)
                        ->update(['image_path' => $first]);
                }
            });

        Schema::table('business_catalog_items', function (Blueprint $table): void {
            $table->dropColumn('image_paths');
        });
    }
};
