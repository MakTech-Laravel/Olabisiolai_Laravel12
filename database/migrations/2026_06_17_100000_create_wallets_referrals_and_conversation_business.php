<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('type', 16);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('description');
            $table->string('reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'created_at']);
        });

        Schema::create('referral_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->timestamps();
        });

        Schema::create('referral_invites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitee_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('code', 32);
            $table->string('status', 24)->default('pending');
            $table->decimal('credited_amount', 12, 2)->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->string('invitee_email')->nullable();
            $table->timestamps();

            $table->index(['referrer_user_id', 'status']);
            $table->index('code');
        });

        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'business_info_id')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->foreignId('business_info_id')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('business_info')
                    ->nullOnDelete();
                $table->index('business_info_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'business_info_id')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('business_info_id');
            });
        }

        Schema::dropIfExists('referral_invites');
        Schema::dropIfExists('referral_codes');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
