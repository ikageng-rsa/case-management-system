<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function() {
    Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');
    Route::view('/matters', 'matters')->name('matters');
    Route::view('/matters/:id', 'matter-show')->name('matter.show');
    Route::view('/clients', 'clients')->name('clients');
    Route::view('/documents', 'documents')->name('documents');
    Route::view('/users', 'users')->name('users');
});

