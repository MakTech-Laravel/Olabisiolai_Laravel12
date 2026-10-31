<?php

use App\Enums\BusinessStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cat = Category::query()->firstOrCreate(
    ['name' => 'Gifts and Sourvenirs Shop'],
    ['subcategories' => ['Souvenirs'], 'icon' => null],
);
$retail = Category::query()->firstOrCreate(
    ['name' => 'Shopping and Retail Store'],
    ['subcategories' => ['Retail'], 'icon' => null],
);
$loc = Location::query()->first() ?? Location::factory()->create([
    'lga_name' => 'Ikorodu',
    'city_name' => 'Ikorodu',
    'state_name' => 'Lagos',
]);
$user = User::factory()->create(['role' => 'vendor']);

foreach (
    [
        ['Akin Delight Photogifts and Concepts', $cat->id],
        ['NELIZ HUB VENTURE', $retail->id],
        ['Strandsbyel Beauty', $retail->id],
    ] as [$name, $cid]
) {
    BusinessInfo::factory()->for($user)->create([
        'category_id' => $cid,
        'location_id' => $loc->id,
        'business_name' => $name,
        'business_status' => BusinessStatus::Active,
        'business_description' => 'Photogifts and framing and retail.',
        'services_offered' => ['Uncategorized services'],
    ]);
}

echo "seeded ok\n";
