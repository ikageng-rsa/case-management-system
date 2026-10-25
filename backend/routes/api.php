<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Account\CurrentUserController;
use App\Http\Controllers\Api\V1\Auth\ResendSignInCodeController;
use App\Http\Controllers\Api\V1\Auth\SignInController;
use App\Http\Controllers\Api\V1\Auth\SignOutController;
use App\Http\Controllers\Api\V1\Auth\VerifySignInCodeController;
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
    });
});
