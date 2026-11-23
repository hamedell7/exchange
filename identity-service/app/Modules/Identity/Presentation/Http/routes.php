<?php

declare(strict_types=1);

use App\Modules\Identity\Presentation\Http\Controllers\Api\V1\Identity\EmailVerificationController;
use App\Modules\Identity\Presentation\Http\Controllers\Api\V1\Identity\ListUsersController;
use App\Modules\Identity\Presentation\Http\Controllers\Api\V1\Identity\PasswordResetController;
use App\Modules\Identity\Presentation\Http\Controllers\Api\V1\Identity\RegistrationController;
use App\Modules\Identity\Presentation\Http\Middleware\RequireIdentityAdminReadToken;
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

    Route::get('/users', ListUsersController::class)
        ->middleware([
            'throttle:60,1',
            RequireIdentityAdminReadToken::class,
        ])
        ->name('identity.users.index');
});
