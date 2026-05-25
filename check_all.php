<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "All Businesses:\n";
echo "==============\n";

$businesses = App\Models\BusinessInfo::select('id', 'business_name', 'logo_path')->orderBy('id')->get();

foreach ($businesses as $business) {
    echo "ID: {$business->id} - Name: {$business->business_name} - Image: " . ($business->logo_path ?? 'NULL') . "\n";
}
