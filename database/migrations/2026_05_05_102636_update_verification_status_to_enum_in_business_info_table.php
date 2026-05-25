<?php

use App\Enums\VerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baseline `create_business_info_table` stores `verification_status` as a string
     * with default `VerificationStatus::None`. This migration upgrades the column to a
     * native MySQL ENUM (skipped when already an ENUM, e.g. re-run safety).
     */
    public function up(): void
    {
        if (! Schema::hasTable('business_info')) {
            return;
        }

        if ($this->verificationStatusColumnIsAlreadyMysqlEnum()) {
            return;
        }

        Schema::table('business_info', function (Blueprint $table): void {
            $table->enum('verification_status', ['none', 'pending', 'approved', 'rejected'])
                ->default('none')
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_info')) {
            return;
        }

        Schema::table('business_info', function (Blueprint $table): void {
            $table->string('verification_status')->default(VerificationStatus::None->value)->change();
        });
    }

    private function verificationStatusColumnIsAlreadyMysqlEnum(): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }

        $table = Schema::getConnection()->getQueryGrammar()->wrapTable('business_info');
        $column = collect(DB::select("SHOW COLUMNS FROM {$table} WHERE Field = ?", ['verification_status']))->first();

        if ($column === null) {
            return false;
        }

        $type = strtolower((string) ($column->Type ?? ''));

        return str_starts_with($type, 'enum(');
    }
};
