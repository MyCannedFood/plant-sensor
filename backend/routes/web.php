<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This application is an API. The dashboard lives in the separate Vue
| project (../frontend). Sanctum registers GET /sanctum/csrf-cookie itself.
|
*/

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api'),
]));
