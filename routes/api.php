<?php

use App\Http\Controllers\Api\TrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Tracking pixel API endpoints. These are stateless and handle CORS.
|
*/

// Serve the tracking pixel JavaScript
Route::get('/pixel/{pixelCode}.js', [TrackingController::class, 'servePixel'])
    ->name('pixel.script');

// Tracking endpoints
Route::prefix('track')->group(function () {
    Route::post('/pageview', [TrackingController::class, 'pageview'])->name('track.pageview');
    Route::post('/form', [TrackingController::class, 'form'])->name('track.form');
    Route::post('/click', [TrackingController::class, 'click'])->name('track.click');
    Route::post('/engagement', [TrackingController::class, 'engagement'])->name('track.engagement');
    Route::post('/event', [TrackingController::class, 'event'])->name('track.event');
    Route::post('/identify', [TrackingController::class, 'identify'])->name('track.identify');
});
