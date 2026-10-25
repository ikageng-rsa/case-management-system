<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\ResendSignInCodeController;
use App\Http\Controllers\Api\V1\Auth\SignInController;
use App\Http\Controllers\Api\V1\Auth\VerifySignInCodeController;
use Illuminate\Support\Facades\Route;

// The mobile app's API. Only day-to-day work on matters is offered here; the rest stays on the web.
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    // Signing in: the password, then the emailed code, which gives the device its token.
    Route::middleware('throttle:api-sign-in')->group(function () {
        Route::post('/auth/sign-in', [SignInController::class, 'store'])->name('api.sign-in');
        Route::post('/auth/sign-in/verify', VerifySignInCodeController::class)->name('api.sign-in.verify');
        Route::post('/auth/sign-in/resend', ResendSignInCodeController::class)->name('api.sign-in.resend');
    });
});
