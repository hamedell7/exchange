<?php

namespace App\Providers;

use App\Modules\Identity\Application\Contracts\PasswordHasher;
use App\Modules\Identity\Application\Contracts\UserIdGenerator;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Infrastructure\Hashing\LaravelPasswordHasher;
use App\Modules\Identity\Infrastructure\Identifiers\UlidUserIdGenerator;
use App\Modules\Identity\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->bind(UserIdGenerator::class, UlidUserIdGenerator::class);
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
