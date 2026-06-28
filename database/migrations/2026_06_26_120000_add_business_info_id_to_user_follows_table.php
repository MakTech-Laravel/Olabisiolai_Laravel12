<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_follows', 'business_info_id')) {
            Schema::table('user_follows', function (Blueprint $table) {
                $table->foreignId('business_info_id')
                    ->nullable()
                    ->after('following_id')
                    ->constrained('business_info')
                    ->cascadeOnDelete();
            });
        }

        DB::table('user_follows')->orderBy('id')->chunkById(100, function ($follows): void {
            foreach ($follows as $follow) {
                if ($follow->business_info_id !== null) {
                    continue;
                }

                $businessId = DB::table('business_info')
                    ->where('user_id', $follow->following_id)
                    ->orderBy('id')
                    ->value('id');

                if ($businessId !== null) {
                    DB::table('user_follows')
                        ->where('id', $follow->id)
                        ->update(['business_info_id' => $businessId]);
                }
            }
        });

        Schema::table('user_follows', function (Blueprint $table) {
            $table->dropForeign(['follower_id']);
            $table->dropForeign(['following_id']);
        });

        Schema::table('user_follows', function (Blueprint $table) {
            $table->dropUnique(['follower_id', 'following_id']);
            $table->unique(['follower_id', 'business_info_id']);
            $table->index('business_info_id');
        });

        Schema::table('user_follows', function (Blueprint $table) {
            $table->foreign('follower_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('following_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_follows', function (Blueprint $table) {
            $table->dropForeign(['follower_id']);
            $table->dropForeign(['following_id']);
            $table->dropForeign(['business_info_id']);
        });

        Schema::table('user_follows', function (Blueprint $table) {
            $table->dropUnique(['follower_id', 'business_info_id']);
            $table->dropIndex(['business_info_id']);
            $table->dropColumn('business_info_id');
            $table->unique(['follower_id', 'following_id']);
        });

        Schema::table('user_follows', function (Blueprint $table) {
            $table->foreign('follower_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('following_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
