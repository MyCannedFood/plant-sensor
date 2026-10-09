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

// The ingest endpoint. The rate limiter joins the middleware stack in the
// same file this alias lives in; both are registered in bootstrap/app.php.
Route::post('/readings', [ReadingController::class, 'store'])
    ->middleware('device.auth')
    ->name('readings.store');
