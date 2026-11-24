<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Identity\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Identity user record. Laravel's Eloquent model is used directly by the
 * Identity services; API responses are still exposed through explicit Resources.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'email_normalized'])]
class User extends Authenticatable
{
    public const MIN_NAME_LENGTH = 2;
    public const MAX_NAME_LENGTH = 255;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }
}
