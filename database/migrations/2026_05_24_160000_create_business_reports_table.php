<?php

use App\Enums\ReviewReportReason;
use App\Enums\ReviewReportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_info_id')->constrained('business_info')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason')->default(ReviewReportReason::Other->value);
            $table->text('description')->nullable();
            $table->string('status')->default(ReviewReportStatus::Pending->value);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_info_id', 'user_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_reports');
    }
};
