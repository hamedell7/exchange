<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User as EloquentUser;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reconstitutes_user_aggregates_by_id_and_normalized_email(): void
    {
        $record = EloquentUser::factory()->create([
            'name' => 'Alice Example',
            'email' => 'Alice@example.com',
            'status' => 'active',
        ]);

        $repository = $this->app->make(UserRepository::class);

        self::assertInstanceOf(EloquentUserRepository::class, $repository);

        $byId = $repository->findById(new UserId((string) $record->getKey()));

        self::assertNotNull($byId);
        self::assertSame((string) $record->getKey(), $byId->id()->value());
        self::assertSame('Alice Example', $byId->name());
        self::assertSame('Alice@example.com', $byId->email()->value());

        $byEmail = $repository->findByEmail(new EmailAddress(' ALICE@EXAMPLE.COM '));

        self::assertNotNull($byEmail);
        self::assertSame((string) $record->getKey(), $byEmail->id()->value());

        self::assertNull($repository->findById(new UserId('01JCS7ZJ4M6P7W8X9Y0Z1A2B3C')));
        self::assertNull($repository->findByEmail(new EmailAddress('missing@example.com')));
    }
}
