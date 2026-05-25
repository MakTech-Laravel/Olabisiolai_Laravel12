<?php

use App\Enums\VerificationNoteType;
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
        Schema::create('verification_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sort_order')->default(0);
            $table->unsignedBigInteger('business_info_id');
            $table->unsignedBigInteger('added_by');
            $table->string('note_type')->default(VerificationNoteType::Internal->value);
            $table->text('note');
            $table->json('metadata')->nullable();
            $table->boolean('is_visible_to_vendor')->default(false);
            $table->timestamps();
            $table->foreign('business_info_id')->references('id')->on('business_info')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('added_by')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();

            $table->index(['business_info_id', 'is_visible_to_vendor']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_notes');
    }
};
