<?php

use App\Livewire\AlertHistory;
use App\Livewire\Overview;
use App\Livewire\PlantMonitor;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', Overview::class)->name('overview');
Route::get('/plants/{plant}', PlantMonitor::class)->name('plants.monitor');
Route::get('/alerts', AlertHistory::class)->name('alerts');
