<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiaryEntryController;
use App\Http\Controllers\MatterController;
use App\Http\Controllers\MatterDocumentController;
use App\Http\Controllers\NarrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');
    Route::get('/matters', [MatterController::class, 'index'])->name('matters');
    Route::post('/matters', [MatterController::class, 'store'])->name('matter.store');

    Route::post('/matters/{matter}/close', [MatterController::class, 'close'])->name('matter.close');
    Route::post('/matters/{matter}/assign', [MatterController::class, 'assign'])->name('matter.assign');
    Route::delete('/matters/{matter}/assign', [MatterController::class, 'unassign'])->name('matter.unassign');
    Route::patch('/matters/{matter}', [MatterController::class, 'update'])->name('matter.update');
    Route::get('/matters/{matter}', [MatterController::class, 'show'])->name('matter.show');

    Route::post('/matters/{matter}/narrations', [NarrationController::class, 'store'])->name('matter.narrations.store');
    Route::get('/matters/{matter}/narrations/export', [NarrationController::class, 'export'])->name('matter.narrations.export');

    Route::post('/matters/{matter}/diary', [DiaryEntryController::class, 'store'])->name('matter.diary.store');
    Route::post('/matters/{matter}/diary/{diaryEntry}/complete', [DiaryEntryController::class, 'complete'])->name('matter.diary.complete');

    Route::post('/matters/{matter}/documents', [MatterDocumentController::class, 'store'])->name('matter.documents.store');
    Route::get('/matters/{matter}/documents/{document}', [MatterDocumentController::class, 'download'])->name('matter.documents.download');
    Route::delete('/matters/{matter}/documents/{document}', [MatterDocumentController::class, 'destroy'])->name('matter.documents.destroy');

    Route::view('/clients', 'clients')->name('clients');
    Route::view('/documents', 'documents')->name('documents');
    Route::view('/users', 'users')->name('users');
    Route::view('/audit-log', 'audit-log')->name('audit-log');
});
