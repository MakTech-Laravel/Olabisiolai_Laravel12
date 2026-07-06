<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_trials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_info_id')->constrained('business_info')->cascadeOnDelete();
            $table->foreignId('pricing_package_id')->constrained('pricing_packages')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ends_at');
            $table->string('ended_reason', 20)->nullable();
            $table->timestamps();

            $table->index(['business_info_id', 'pricing_package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_trials');
    }
};
