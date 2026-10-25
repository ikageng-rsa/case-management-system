<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Account\CurrentUserController;
use App\Http\Controllers\Api\V1\Auth\ResendSignInCodeController;
use App\Http\Controllers\Api\V1\Auth\SignInController;
use App\Http\Controllers\Api\V1\Auth\SignOutController;
use App\Http\Controllers\Api\V1\Auth\VerifySignInCodeController;
use App\Http\Controllers\Api\V1\Reference\ActivityTypeController;
use App\Http\Controllers\Api\V1\Reference\AssignmentCapacityController;
use App\Http\Controllers\Api\V1\Reference\DocumentKindController;
use App\Http\Controllers\Api\V1\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::middleware('throttle:api-sign-in')->group(function () {
        Route::post('/auth/sign-in', [SignInController::class, 'store'])->name('api.sign-in');
        Route::post('/auth/sign-in/verify', VerifySignInCodeController::class)->name('api.sign-in.verify');
        Route::post('/auth/sign-in/resend', ResendSignInCodeController::class)->name('api.sign-in.resend');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/sign-out', SignOutController::class)->name('api.sign-out');
        Route::get('/me', CurrentUserController::class)->name('api.me');

        // The lists the app's forms are filled from. Read only: they are kept on the web.
        Route::get('/activity-types', [ActivityTypeController::class, 'index'])->name('api.activity-types');
        Route::get('/assignment-capacities', [AssignmentCapacityController::class, 'index'])->name('api.assignment-capacities');
        Route::get('/document-kinds', [DocumentKindController::class, 'index'])->name('api.document-kinds');
        Route::get('/users', [UserController::class, 'index'])->name('api.users');
    });
});
