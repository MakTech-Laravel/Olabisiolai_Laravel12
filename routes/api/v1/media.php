<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\MediaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Media upload API (role: user, vendor)
| Middleware applied in routes/api.php: auth:api, role:user,vendor
|--------------------------------------------------------------------------
*/

Route::post('media', [MediaController::class, 'store'])
    ->middleware(['throttle:60,1'])
    ->name('media.store');
