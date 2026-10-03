<?php

use App\Http\Controllers\PrometheusMetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/metrics', PrometheusMetricsController::class)->name('metrics');

Route::get('/', function () {
    return view('app');
});

Route::fallback(function () {
    return view('app');
});
