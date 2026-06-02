<?php

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sort_order')->default(0);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('business_info_id')->nullable();
            /** verification, boost, subscription, etc. */
            $table->string('purpose');
            $table->string('package_id')->nullable();
            $table->decimal('amount');
            $table->string('currency')->default('NGN');
            $table->string('tx_ref')->unique();
            $table->string('gateway')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->string('status')->default(PaymentStatus::Pending->value);
            $table->timestamp('paid_at')->nullable();
            $table->boolean('is_consumed')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('business_info_id')->references('id')->on('business_info')->cascadeOnDelete()->cascadeOnUpdate();

            $table->index(['user_id', 'purpose', 'status']);
            $table->index(['business_info_id', 'purpose', 'status']);
            $table->index(['gateway', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
