<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('uploadable');
            $table->string('uploadable_type_key', 50);
            $table->string('path', 500);
            $table->string('disk', 50)->default('public');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->char('file_hash', 40);
            $table->unsignedBigInteger('size_before');
            $table->unsignedBigInteger('size_after')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(
                ['uploadable_type', 'uploadable_id', 'file_hash'],
                'media_uploadable_hash_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
