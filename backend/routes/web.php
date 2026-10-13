<?php

declare(strict_types=1);

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientRevealController;
use App\Http\Controllers\ContactPersonController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiaryEntryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\MatterController;
use App\Http\Controllers\MatterDocumentController;
use App\Http\Controllers\NarrationController;
use App\Http\Controllers\PopiaConsentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');
    Route::get('/matters', [MatterController::class, 'index'])->name('matters');
    Route::post('/matters', [MatterController::class, 'store'])->name('matter.store');

    // Likewise, ahead of the matter routes.
    Route::get('/matters/search', [MatterController::class, 'search'])->name('matter.search');
    Route::get('/matters/reference-preview', [MatterController::class, 'previewReference'])->name('matter.reference-preview');
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

    Route::get('/clients', [ClientController::class, 'index'])->name('clients');
    Route::post('/clients', [ClientController::class, 'store'])->name('client.store');
    // Declared before the client route, which would otherwise take "lookup" for an id.
    Route::get('/clients/lookup', [ClientController::class, 'lookup'])->name('client.lookup');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('client.show');
    Route::patch('/clients/{client}', [ClientController::class, 'update'])->name('client.update');
    Route::post('/clients/{client}/reveal', ClientRevealController::class)->name('client.reveal');
    Route::post('/clients/{client}/contacts', [ClientContactController::class, 'store'])->name('client.contacts.store');
    Route::patch('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'update'])->name('client.contacts.update');
    Route::delete('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'destroy'])->name('client.contacts.destroy');
    Route::post('/clients/{client}/contact-persons', [ContactPersonController::class, 'store'])->name('client.persons.store');
    Route::patch('/clients/{client}/contact-persons/{person}', [ContactPersonController::class, 'update'])->name('client.persons.update');
    Route::delete('/clients/{client}/contact-persons/{person}', [ContactPersonController::class, 'destroy'])->name('client.persons.destroy');
    Route::post('/clients/{client}/popia-consent', [PopiaConsentController::class, 'store'])->name('client.consent.store');
    Route::post('/clients/{client}/popia-consent/withdraw', [PopiaConsentController::class, 'withdraw'])->name('client.consent.withdraw');
    Route::get('/clients/{client}/popia-consent/{popiaConsent}/mandate', [PopiaConsentController::class, 'mandate'])->name('client.consent.mandate');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');

    // Firm administration: who may do what, and the record of what was done.
    Route::get('/users', [UserController::class, 'index'])->middleware('can:manage_users')->name('users');

    Route::middleware('can:assign_roles')->group(function () {
        Route::patch('/users/{user}/roles', [UserController::class, 'updateRoles'])->name('user.roles.update');
        Route::post('/roles', [UserController::class, 'storeRole'])->name('role.store');
        Route::patch('/roles/{role}', [UserController::class, 'updateRole'])->name('role.update');
        Route::delete('/roles/{role}', [UserController::class, 'destroyRole'])->name('role.destroy');
    });

    Route::get('/audit-log', [AuditLogController::class, 'index'])->middleware('can:view_audit_log')->name('audit-log');
    Route::get('/audit-log/export', [AuditLogController::class, 'export'])->middleware('can:export_audit_log')->name('audit-log.export');
});
