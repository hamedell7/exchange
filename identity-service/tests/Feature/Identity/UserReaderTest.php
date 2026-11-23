<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User as EloquentUser;
use App\Modules\Identity\Application\Contracts\UserReader;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_user_aggregates_by_id_and_normalized_email(): void
    {
        $record = EloquentUser::factory()->create([
            'name' => 'Alice Example',
            'email' => 'Alice@example.com',
            'status' => 'active',
        ]);

        $reader = $this->app->make(UserReader::class);

        $byId = $reader->findById(new UserId((string) $record->getKey()));

        self::assertNotNull($byId);
        self::assertSame((string) $record->getKey(), $byId->id()->value());
        self::assertSame('Alice Example', $byId->name());
        self::assertSame('Alice@example.com', $byId->email()->value());

        $byEmail = $reader->findByEmail(new EmailAddress(' ALICE@EXAMPLE.COM '));

        self::assertNotNull($byEmail);
        self::assertSame((string) $record->getKey(), $byEmail->id()->value());

        self::assertNull($reader->findById(new UserId('01JCS7ZJ4M6P7W8X9Y0Z1A2B3C')));
        self::assertNull($reader->findByEmail(new EmailAddress('missing@example.com')));
    }
}
