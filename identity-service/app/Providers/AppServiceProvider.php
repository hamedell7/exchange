<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('identity-email-actions', function (Request $request): array {
            $email = strtolower(trim((string) $request->input('email', '')));
            $emailKey = $email === '' ? $request->ip() : hash('sha256', $email);

            return [
                Limit::perMinute(10)->by('identity-email-actions:ip:'.$request->ip()),
                Limit::perMinute(3)->by('identity-email-actions:email:'.$emailKey),
            ];
        });
    }
}
