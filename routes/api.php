<?php

use App\Http\Controllers\Api\SatpamTelurController;
use App\Http\Middleware\SatpamApiKey;
use Illuminate\Support\Facades\Route;

// URL: GET /api/satpam/harga, POST /api/satpam/nota  (header: X-Api-Key)
Route::prefix('satpam')->middleware([SatpamApiKey::class])->group(function () {
    Route::get('harga', [SatpamTelurController::class, 'harga'])->middleware('throttle:300,1');
    Route::post('nota', [SatpamTelurController::class, 'store'])->middleware('throttle:60,1');
});