<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MatterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');
    Route::get('/matters', [MatterController::class, 'index'])->name('matters');
    Route::post('/matters', [MatterController::class, 'store'])->name('matter.store');
    
    // Intentionally declaring the routes before matter show so its match doesn't swallow them
    Route::post('/matters/{matter}/close', [MatterController::class, 'close'])->where('matter', '.+')->name('matter.close');
    Route::post('/matters/{matter}/assign', [MatterController::class, 'assign'])->where('matter', '.+')->name('matter.assign');
    Route::delete('/matters/{matter}/assign', [MatterController::class, 'unassign'])->where('matter', '.+')->name('matter.unassign');
    Route::patch('/matters/{matter}', [MatterController::class, 'update'])->where('matter', '.+')->name('matter.update');
    Route::get('/matters/{matter}', [MatterController::class, 'show'])->where('matter', '.+')->name('matter.show');
    
    Route::view('/clients', 'clients')->name('clients');
    Route::view('/documents', 'documents')->name('documents');
    Route::view('/users', 'users')->name('users');
    Route::view('/audit-log', 'audit-log')->name('audit-log');
});
