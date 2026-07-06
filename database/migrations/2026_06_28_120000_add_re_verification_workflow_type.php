<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE verification_workflows MODIFY COLUMN workflow_type ENUM(
            'initial_submission',
            're_verification',
            'document_review',
            'background_check',
            'site_visit',
            'final_approval'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE verification_workflows MODIFY COLUMN workflow_type ENUM(
            'initial_submission',
            'document_review',
            'background_check',
            'site_visit',
            'final_approval'
        ) NOT NULL");
    }
};
