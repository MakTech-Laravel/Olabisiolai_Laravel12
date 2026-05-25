<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('label', 80)->nullable();
            $table->string('cardholder_name', 160);
            $table->string('email');
            $table->string('phone', 32);
            $table->string('last_four', 4)->nullable();
            $table->string('card_brand', 48)->nullable();
            $table->string('exp_month', 2)->nullable();
            $table->string('exp_year', 4)->nullable();
            $table->string('billing_line1', 255)->nullable();
            $table->string('billing_city', 120)->nullable();
            $table->string('billing_state', 120)->nullable();
            $table->string('billing_country', 120)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_methods');
    }
};
