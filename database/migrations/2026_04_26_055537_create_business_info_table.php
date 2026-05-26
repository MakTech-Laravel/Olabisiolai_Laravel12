<?php

use App\Enums\BusinessStatus;
use App\Enums\VerificationStatus;
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
        Schema::create('business_info', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sort_order')->default(0);
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('category_id');
            $table->string('subcategory')->nullable();
            $table->string('business_name');
            $table->string('street_address')->nullable();
            $table->text('business_description');
            $table->json('services_offered');
            $table->string('phone');
            $table->string('whatsapp')->nullable();
            $table->string('website')->nullable();
            $table->json('social_accounts')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('cover_photo_paths')->nullable();
            $table->string('verification_status')->default(VerificationStatus::None->value);
            $table->boolean('is_flagged')->default(false);
            $table->string('business_status', 20)->default(BusinessStatus::Active->value);
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();

            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('verified_by')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();

            $table->unique('user_id');
            $table->index('category_id');
            $table->index('verification_status');
            $table->index('is_flagged');
            $table->index('business_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_info');
    }
};
