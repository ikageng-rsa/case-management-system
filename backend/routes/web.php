<?php

declare(strict_types=1);

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivityTypeController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Client\ClientContactController;
use App\Http\Controllers\Client\ClientController;
use App\Http\Controllers\Client\ClientFicaDocumentController;
use App\Http\Controllers\Client\ClientRevealController;
use App\Http\Controllers\Client\ContactPersonController;
use App\Http\Controllers\Client\PopiaConsentController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiaryEntryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Matter\MatterController;
use App\Http\Controllers\Matter\MatterDocumentController;
use App\Http\Controllers\Matter\MatterPartyController;
use App\Http\Controllers\MatterTypeController;
use App\Http\Controllers\NarrationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\SignInCodeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');

    return view('welcome');
});

// The second step of signing in: the code that was emailed. Reached only after the password.
Route::middleware(['guest', 'throttle:two-factor'])->group(function () {
    Route::get('/sign-in/code', [SignInCodeController::class, 'show'])->name('sign-in-code');
    Route::post('/sign-in/code', [SignInCodeController::class, 'verify'])->name('sign-in-code.verify');
    Route::post('/sign-in/code/resend', [SignInCodeController::class, 'resend'])->name('sign-in-code.resend');
});

// Where an invitation's link lands. Guests only, and throttled: the token is the whole secret.
Route::middleware(['guest', 'throttle:invitations'])->group(function () {
    Route::get('/invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitation.show');
    Route::post('/invitations/{token}', [AcceptInvitationController::class, 'store'])->name('invitation.accept');
});

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::patch('/account/notifications', [AccountController::class, 'updateNotifications'])->name('account.notifications');
    Route::patch('/account/sign-in-code', [AccountController::class, 'updateSignInCode'])->name('account.sign-in-code');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');
    Route::get('/matters', [MatterController::class, 'index'])->name('matters');
    Route::post('/matters', [MatterController::class, 'store'])->name('matter.store');

    // add matters* before matter so it doesn't consume the other routes
    Route::get('/matters/search', [MatterController::class, 'search'])->name('matter.search');
    Route::get('/matters/reference-preview', [MatterController::class, 'previewReference'])->name('matter.reference-preview');
    Route::post('/matters/{matter}/close', [MatterController::class, 'close'])->name('matter.close');
    Route::post('/matters/{matter}/reopen', [MatterController::class, 'reopen'])->name('matter.reopen');
    Route::post('/matters/{matter}/assign', [MatterController::class, 'assign'])->name('matter.assign');
    Route::delete('/matters/{matter}/assign', [MatterController::class, 'unassign'])->name('matter.unassign');
    Route::patch('/matters/{matter}', [MatterController::class, 'update'])->name('matter.update');
    Route::get('/matters/{matter}', [MatterController::class, 'show'])->name('matter.show');

    Route::post('/matters/{matter}/parties', [MatterPartyController::class, 'store'])->name('matter.parties.store');
    Route::patch('/matters/{matter}/parties/{party}', [MatterPartyController::class, 'update'])->name('matter.parties.update');
    Route::delete('/matters/{matter}/parties/{party}', [MatterPartyController::class, 'destroy'])->name('matter.parties.destroy');

    Route::post('/matters/{matter}/narrations', [NarrationController::class, 'store'])->name('matter.narrations.store');
    Route::get('/matters/{matter}/narrations/export', [NarrationController::class, 'export'])->name('matter.narrations.export');

    Route::post('/matters/{matter}/diary', [DiaryEntryController::class, 'store'])->name('matter.diary.store');
    Route::patch('/matters/{matter}/diary/{diaryEntry}/reminder', [DiaryEntryController::class, 'updateReminder'])->name('matter.diary.reminder');
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
    Route::post('/clients/{client}/fica-documents', [ClientFicaDocumentController::class, 'store'])->name('client.fica.store');
    Route::get('/clients/{client}/fica-documents/{document}', [ClientFicaDocumentController::class, 'download'])->name('client.fica.download');
    Route::delete('/clients/{client}/fica-documents/{document}', [ClientFicaDocumentController::class, 'destroy'])->name('client.fica.destroy');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');

    // Firm administration: who may do what, and the record of what was done.
    Route::get('/users', [UserController::class, 'index'])->middleware('can:view_users')->name('users');
    Route::patch('/users/{user}', [UserController::class, 'updateDetails'])->middleware('can:edit_users')->name('user.details.update');

    Route::middleware('can:invite_users')->group(function () {
        Route::post('/invitations', [InvitationController::class, 'store'])->name('invitation.store');
        Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitation.resend');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitation.destroy');
    });

    Route::middleware('can:deactivate_users')->group(function () {
        Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('user.deactivate');
        Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('user.reactivate');
    });
    Route::patch('/users/{user}/roles', [UserController::class, 'updateRoles'])->middleware('can:assign_roles')->name('user.roles.update');

    Route::middleware('can:manage_roles')->group(function () {
        Route::post('/roles', [UserController::class, 'storeRole'])->name('role.store');
        Route::patch('/roles/{role}', [UserController::class, 'updateRole'])->name('role.update');
        Route::delete('/roles/{role}', [UserController::class, 'destroyRole'])->name('role.destroy');
    });

    // The lists the forms are filled from. Each table is its own permission.
    Route::get('/reference-data', [ReferenceDataController::class, 'index'])->name('reference-data');

    Route::middleware('can:manage_courts')->group(function () {
        Route::post('/courts', [CourtController::class, 'store'])->name('court.store');
        Route::patch('/courts/{court}', [CourtController::class, 'update'])->name('court.update');
        Route::delete('/courts/{court}', [CourtController::class, 'destroy'])->name('court.destroy');
        Route::post('/courts/{court}/restore', [CourtController::class, 'restore'])->withTrashed()->name('court.restore');
    });

    Route::middleware('can:manage_matter_types')->group(function () {
        Route::post('/matter-types', [MatterTypeController::class, 'store'])->name('matter-type.store');
        Route::patch('/matter-types/{matterType}', [MatterTypeController::class, 'update'])->name('matter-type.update');
        Route::delete('/matter-types/{matterType}', [MatterTypeController::class, 'destroy'])->name('matter-type.destroy');
        Route::post('/matter-types/{matterType}/restore', [MatterTypeController::class, 'restore'])->withTrashed()->name('matter-type.restore');
    });

    Route::middleware('can:manage_activity_types')->group(function () {
        Route::post('/activity-types', [ActivityTypeController::class, 'store'])->name('activity-type.store');
        Route::patch('/activity-types/{activityType}', [ActivityTypeController::class, 'update'])->name('activity-type.update');
        Route::delete('/activity-types/{activityType}', [ActivityTypeController::class, 'destroy'])->name('activity-type.destroy');
        Route::post('/activity-types/{activityType}/restore', [ActivityTypeController::class, 'restore'])->withTrashed()->name('activity-type.restore');
    });

    Route::get('/audit-log', [AuditLogController::class, 'index'])->middleware('can:view_audit_log')->name('audit-log');
    Route::get('/audit-log/export', [AuditLogController::class, 'export'])->middleware('can:export_audit_log')->name('audit-log.export');
});
