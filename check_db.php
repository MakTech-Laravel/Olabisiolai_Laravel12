<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking Database Logo Paths:\n";
echo "============================\n";

$businesses = App\Models\BusinessInfo::select('id', 'business_name', 'logo_path')->take(2)->get();

foreach ($businesses as $business) {
    echo "ID: {$business->id}\n";
    echo "Name: {$business->business_name}\n";
    echo "Logo Path: " . ($business->logo_path ?? 'NULL') . "\n";
    echo "Is Empty: " . (empty($business->logo_path) ? 'YES' : 'NO') . "\n";
    echo "---------------------\n";
}
