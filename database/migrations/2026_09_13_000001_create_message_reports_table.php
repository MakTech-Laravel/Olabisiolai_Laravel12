<?php

use App\Enums\MessageReportReason;
use App\Enums\ReviewReportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason')->default(MessageReportReason::Other->value);
            $table->text('description')->nullable();
            $table->string('status')->default(ReviewReportStatus::Pending->value);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->string('last_admin_email_subject')->nullable();
            $table->text('last_admin_email_body')->nullable();
            $table->timestamp('last_admin_email_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'reporter_user_id']);
            $table->index(['status', 'created_at']);
            $table->index(['reported_user_id', 'status']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reports');
    }
};
