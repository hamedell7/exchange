<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Application\Contracts\AccountActionTokenRepository;
use App\Modules\Identity\Application\Contracts\Clock;
use App\Modules\Identity\Application\Contracts\IdentityMailer;
use App\Modules\Identity\Application\Contracts\PasswordHasher;
use App\Modules\Identity\Application\Contracts\TokenGenerator;
use App\Modules\Identity\Application\Contracts\TransactionRunner;
use App\Modules\Identity\Application\Contracts\UserIdGenerator;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Infrastructure\Clock\SystemClock;
use App\Modules\Identity\Infrastructure\Database\LaravelTransactionRunner;
use App\Modules\Identity\Infrastructure\Hashing\LaravelPasswordHasher;
use App\Modules\Identity\Infrastructure\Identifiers\UlidUserIdGenerator;
use App\Modules\Identity\Infrastructure\Mail\LaravelIdentityMailer;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentAccountActionTokenRepository;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use App\Modules\Identity\Infrastructure\Tokens\SecureTokenGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->bind(UserIdGenerator::class, UlidUserIdGenerator::class);
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(AccountActionTokenRepository::class, EloquentAccountActionTokenRepository::class);
        $this->app->bind(TokenGenerator::class, SecureTokenGenerator::class);
        $this->app->bind(IdentityMailer::class, LaravelIdentityMailer::class);
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(TransactionRunner::class, LaravelTransactionRunner::class);
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
