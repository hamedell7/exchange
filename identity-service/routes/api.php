<?php

use App\Http\Controllers\Api\V1\Identity\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/identity')->group(function (): void {
    Route::post('/register', RegistrationController::class)
        ->middleware('throttle:registration')
        ->name('identity.register');
});
