<?php

use App\Http\Controllers\Api\AlertsController;
use App\Http\Controllers\Api\RatesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public, rate-limited JSON API consumed by the SPA. Real-time updates are
| pushed separately over the "rates" and "alerts" broadcast channels.
|
*/

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/rates', [RatesController::class, 'index'])->name('rates.index');
    Route::get('/rates/history', [RatesController::class, 'history'])->name('rates.history');
    Route::get('/rates/intraday', [RatesController::class, 'intraday'])->name('rates.intraday');
    Route::get('/rates/timeseries', [RatesController::class, 'timeseries'])->name('rates.timeseries');

    Route::get('/alerts', [AlertsController::class, 'index'])->name('alerts.index');
    Route::post('/alerts', [AlertsController::class, 'store'])->name('alerts.store');
    Route::patch('/alerts/{alert}', [AlertsController::class, 'update'])->name('alerts.update');
    Route::delete('/alerts/{alert}', [AlertsController::class, 'destroy'])->name('alerts.destroy');
});
