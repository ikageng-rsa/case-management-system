<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function() {
    Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');
    Route::view('/matters', 'matters')->name('matter');
});

