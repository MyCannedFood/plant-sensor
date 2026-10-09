<?php

use App\Http\Controllers\ReadingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Device-facing endpoints. Routes here are prefixed with /api, join the
| stateless api middleware group (no session, no CSRF), and render JSON
| errors automatically. Device endpoints are guarded by the device.auth
| alias and a per-device rate limiter, registered in bootstrap/app.php.
|
*/

// A cheap liveness check the simulator can hit before it starts posting.
Route::get('/ping', fn () => response()->json(['status' => 'ok']));

// The ingest endpoint, guarded by the device.auth alias and then by a
// per-device rate limiter (30/min), both registered in bootstrap/app.php.
// Order matters: auth must run first so the limiter can key on the device.
Route::post('/readings', [ReadingController::class, 'store'])
    ->middleware(['device.auth', 'throttle:device-ingest'])
    ->name('readings.store');
