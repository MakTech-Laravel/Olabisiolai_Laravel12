<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profile_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_info_id')->constrained('business_info')->cascadeOnDelete();
            $table->foreignId('viewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('viewer_ip_hash', 64)->nullable();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->index(['business_info_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profile_views');
    }
};
