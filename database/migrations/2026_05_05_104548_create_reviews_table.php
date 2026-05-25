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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('business_id');
            $table->string('full_name');
            $table->boolean('is_anonymous')->default(false);
            $table->unsignedTinyInteger('rating'); // 1-5 stars
            $table->text('review_text');
            $table->boolean('is_approved')->default(true);
            $table->text('flag_reason')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('business_info')->onDelete('cascade');

            // Indexes for performance
            $table->index(['business_id', 'user_id', 'is_approved', 'created_at']);
            $table->index('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
