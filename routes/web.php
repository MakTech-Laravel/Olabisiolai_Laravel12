<?php

use App\Http\Controllers\Api\V1\Public\SitemapController;
use App\Http\Controllers\Api\V1\Public\SpaShellController;
use App\Http\Controllers\PublicStorageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/spa-shell', SpaShellController::class)
    ->middleware('throttle:120,1')
    ->name('spa-shell');

Route::get('/storage/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*')
    ->name('public-storage.show');
