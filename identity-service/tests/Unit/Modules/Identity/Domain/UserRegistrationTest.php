<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use App\Modules\Identity\Domain\ValueObjects\PasswordHash;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserRegistrationTest extends TestCase
{
    public function test_email_address_trims_input_and_normalizes_domain_only(): void
    {
        $email = new EmailAddress('  Alice.Example@Example.COM  ');

        self::assertSame('Alice.Example@example.com', $email->value());
    }

    public function test_invalid_email_address_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EmailAddress('not-an-email');
    }

    public function test_user_id_is_an_opaque_non_empty_identifier(): void
    {
        $userId = new UserId(' user-123 ');

        self::assertSame('user-123', $userId->value());

        $this->expectException(InvalidArgumentException::class);

        new UserId('  ');
    }

    public function test_password_hash_rejects_plain_text_and_accepts_encoded_hashes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PasswordHash::fromEncodedHash('plain-text-password');
    }

    public function test_user_aggregate_can_be_created_with_valid_registration_data(): void
    {
        $registeredAt = new DateTimeImmutable('2026-11-21T09:00:00+03:30');
        $id = new UserId('user-123');
        $email = new EmailAddress('alice@example.com');
        $passwordHash = PasswordHash::fromEncodedHash(
            password_hash('test-password', PASSWORD_DEFAULT),
        );

        $user = User::register($id, 'Alice Example', $email, $passwordHash, $registeredAt);

        self::assertTrue($id->equals($user->id()));
        self::assertSame('Alice Example', $user->name());
        self::assertTrue($email->equals($user->email()));
        self::assertSame($passwordHash, $user->passwordHash());
        self::assertSame($registeredAt, $user->registeredAt());
    }
}
