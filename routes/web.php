<?php

use App\Http\Controllers\GoogleAdsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Google Ads OAuth routes
Route::middleware('auth')->prefix('google-ads')->group(function () {
    Route::get('/connect/{tenant}', [GoogleAdsController::class, 'redirect'])->name('google-ads.connect');
    Route::get('/callback', [GoogleAdsController::class, 'callback'])->name('google-ads.callback');
    Route::delete('/disconnect/{account}', [GoogleAdsController::class, 'disconnect'])->name('google-ads.disconnect');
});
