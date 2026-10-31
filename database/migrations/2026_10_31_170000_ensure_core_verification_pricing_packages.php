<?php

use App\Services\PricingPackageService;
use Illuminate\Database\Migrations\Migration;

/**
 * Safely restore/activate core verification tiers on existing databases
 * without overwriting custom live amounts or unrelated rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PricingPackageService::class)->ensureCoreVerificationPackages();
    }

    public function down(): void
    {
        // Non-destructive ensure — nothing to reverse.
    }
};
