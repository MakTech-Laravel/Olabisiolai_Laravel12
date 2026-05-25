<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('admin_vendor_messages');
    }

    public function down(): void
    {
        // Legacy table removed in favour of conversations/messages.
    }
};
