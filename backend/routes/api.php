<?php

use App\Http\Controllers\Dashboard\AlertController;
use App\Http\Controllers\Dashboard\AuthController;
use App\Http\Controllers\Dashboard\DeviceThresholdController;
use App\Http\Controllers\Dashboard\GoogleAuthController;
use App\Http\Controllers\Dashboard\PlantController;
use App\Http\Controllers\Dashboard\PlantReadingController;
use App\Http\Controllers\ReadingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Two kinds of client talk to this API, and each has its own guard:
|
|  1. Sensor nodes (ESP8266 / ESP32-C3) authenticate with X-Device-Token
|     through the device.auth middleware. Stateless, no cookies.
|
|  2. The Vue dashboard authenticates people with a Sanctum session cookie
|     (auth:sanctum). It calls GET /sanctum/csrf-cookie, then POST /login.
|
*/

// ---------------------------------------------------------------------------
// Devices
// ---------------------------------------------------------------------------

// A cheap liveness check the simulator can hit before it starts posting.
Route::get('/ping', fn () => response()->json(['status' => 'ok']));

// The ingest endpoint, guarded by the device.auth alias and then by a
// per-device rate limiter (30/min), both registered in bootstrap/app.php.
// Order matters: auth must run first so the limiter can key on the device.
Route::post('/readings', [ReadingController::class, 'store'])
    ->middleware(['device.auth', 'throttle:device-ingest'])
    ->name('readings.store');

// Device capabilities endpoint (for simulator to fetch declared sensors)
Route::get('/device/sensors', [ReadingController::class, 'sensors'])
    ->middleware('device.auth')
    ->name('device.sensors');

// ---------------------------------------------------------------------------
// Dashboard (people)
// ---------------------------------------------------------------------------

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login');

// Google OAuth
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('google.login');
// Callback needs web middleware (StartSession) because Google redirects from accounts.google.com,
// so Sanctum's stateful middleware won't recognize it as a stateful request.
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('web')
    ->name('google.callback');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/user', [AuthController::class, 'me'])->name('user');

    Route::apiResource('plants', PlantController::class);
    Route::get('/plants/{plant}/readings', [PlantReadingController::class, 'index'])
        ->name('plants.readings');

    Route::put('/devices/{device}/thresholds', [DeviceThresholdController::class, 'update'])
        ->name('devices.thresholds.update');

    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('/alerts/summary', [AlertController::class, 'summary'])->name('alerts.summary');
});
