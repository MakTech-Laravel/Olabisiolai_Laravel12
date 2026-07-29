<?php

use App\Http\Controllers\Api\V1\BuyerCatalogCartController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Buyer catalog cart (Passport auth) — shared by web + mobile
|--------------------------------------------------------------------------
*/

Route::prefix('cart')->name('cart.')->group(function (): void {
    Route::get('/', [BuyerCatalogCartController::class, 'index'])->name('index');
    Route::post('/items', [BuyerCatalogCartController::class, 'storeItem'])
        ->middleware('throttle:60,1')
        ->name('items.store');
    Route::patch('/items/{id}', [BuyerCatalogCartController::class, 'updateItem'])
        ->whereNumber('id')
        ->middleware('throttle:60,1')
        ->name('items.update');
    Route::delete('/items/{id}', [BuyerCatalogCartController::class, 'destroyItem'])
        ->whereNumber('id')
        ->middleware('throttle:60,1')
        ->name('items.destroy');
    Route::post('/send', [BuyerCatalogCartController::class, 'send'])
        ->middleware('throttle:20,1')
        ->name('send');
});

Route::get('/messages/carts/{cartMessageId}', [BuyerCatalogCartController::class, 'showSent'])
    ->whereNumber('cartMessageId')
    ->name('messages.carts.show');
