<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking Business Images:\n";
echo "======================\n";

$businesses = App\Models\BusinessInfo::select('id', 'business_name', 'logo_path')->take(2)->get();

foreach ($businesses as $business) {
    echo "ID: {$business->id}\n";
    echo "Name: {$business->business_name}\n";
    echo "Image Path: {$business->logo_path}\n";
    
    $imagePath = public_path($business->logo_path);
    echo "Full Path: {$imagePath}\n";
    echo "File Exists: " . (file_exists($imagePath) ? "YES" : "NO") . "\n";
    echo "---------------------\n";
}
