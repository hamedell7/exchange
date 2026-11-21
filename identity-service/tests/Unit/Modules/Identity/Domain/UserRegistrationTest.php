<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserRegistrationTest extends TestCase
{
    public function test_email_address_is_normalized(): void
    {
        $email = new EmailAddress('  Alice.Example@Example.COM  ');

        self::assertSame('alice.example@example.com', $email->value());
    }

    public function test_invalid_email_address_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EmailAddress('not-an-email');
    }

    public function test_user_aggregate_can_be_created_with_valid_registration_data(): void
    {
        $registeredAt = new DateTimeImmutable('2026-11-21T09:00:00+03:30');
        $email = new EmailAddress('alice@example.com');

        $user = User::register(
            '01JCS7ZJ4M6P7W8X9Y0Z1A2B3C',
            $email,
            '$2y$12$example-hash',
            $registeredAt,
        );

        self::assertSame('01JCS7ZJ4M6P7W8X9Y0Z1A2B3C', $user->id());
        self::assertTrue($email->equals($user->email()));
        self::assertSame('$2y$12$example-hash', $user->passwordHash());
        self::assertSame($registeredAt, $user->registeredAt());
    }

    public function test_user_cannot_be_registered_without_an_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        User::register(
            ' ',
            new EmailAddress('alice@example.com'),
            '$2y$12$example-hash',
            new DateTimeImmutable(),
        );
    }

    public function test_user_cannot_be_registered_without_a_password_hash(): void
    {
        $this->expectException(InvalidArgumentException::class);

        User::register(
            '01JCS7ZJ4M6P7W8X9Y0Z1A2B3C',
            new EmailAddress('alice@example.com'),
            ' ',
            new DateTimeImmutable(),
        );
    }
}
