<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payments')
            ->where('purpose', 'boost')
            ->update(['purpose' => 'boosting']);
    }

    public function down(): void
    {
        DB::table('payments')
            ->where('purpose', 'boosting')
            ->update(['purpose' => 'boost']);
    }
};
