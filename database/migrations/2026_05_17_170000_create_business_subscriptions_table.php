<?php

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sort_order')->default(0);
            $table->unsignedBigInteger('business_info_id');
            $table->string('plan', 20)->default(SubscriptionPlan::Free->value);
            $table->string('status', 30)->default(SubscriptionStatus::Active->value);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('business_info_id')->references('id')->on('business_info')->cascadeOnDelete()->cascadeOnUpdate();

            $table->index(['business_info_id', 'plan', 'status']);
            $table->index('expires_at');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_subscriptions');
    }
};
