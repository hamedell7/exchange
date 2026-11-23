<?php

use App\Http\Controllers\Api\V1\Identity\EmailVerificationController;
use App\Http\Controllers\Api\V1\Identity\PasswordResetController;
use App\Http\Controllers\Api\V1\Identity\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/identity')->group(function (): void {
    Route::post('/register', RegistrationController::class)
        ->middleware('throttle:10,1')
        ->name('identity.register');

    Route::post('/email/verification/verify', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('identity.email.verify');

    Route::post('/email/verification/resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:identity-email-actions')
        ->name('identity.email.resend');

    Route::post('/password/forgot', [PasswordResetController::class, 'request'])
        ->middleware('throttle:identity-email-actions')
        ->name('identity.password.forgot');

    Route::post('/password/reset', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:10,1')
        ->name('identity.password.reset');
});
