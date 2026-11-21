<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Commands\RegisterUserCommand;
use App\Modules\Identity\Application\Contracts\PasswordHasher;
use App\Modules\Identity\Application\Contracts\UserIdGenerator;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Application\UseCases\RegisterUser;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\PasswordHash;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RegisterUserTest extends TestCase
{
    public function test_it_registers_a_user_from_a_command(): void
    {
        $userId = new UserId('01JCS7ZJ4M6P7W8X9Y0Z1A2B3C');
        $passwordHash = PasswordHash::fromEncodedHash(
            password_hash('plain-password', PASSWORD_BCRYPT, ['cost' => 4]),
        );

        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())
            ->method('existsByEmail')
            ->with('Alice@example.com')
            ->willReturn(false);

        $registeredUser = null;

        $users->expects(self::once())
            ->method('save')
            ->with($this->callback(
                static function (User $user) use (&$registeredUser): bool {
                    $registeredUser = $user;

                    return true;
                },
            ));

        $passwordHasher = $this->createMock(PasswordHasher::class);
        $passwordHasher->expects(self::once())
            ->method('hash')
            ->with('plain-password')
            ->willReturn($passwordHash);

        $userIds = $this->createMock(UserIdGenerator::class);
        $userIds->expects(self::once())
            ->method('generate')
            ->willReturn($userId);

        $registerUser = new RegisterUser($users, $passwordHasher, $userIds);

        $result = $registerUser->execute(new RegisterUserCommand(
            name: '  Alice Example  ',
            email: ' Alice@example.com ',
            plainPassword: 'plain-password',
        ));

        self::assertSame($registeredUser, $result);
        self::assertSame($userId->value(), $result->id()->value());
        self::assertSame('Alice Example', $result->name());
        self::assertSame('Alice@example.com', $result->email()->value());
        self::assertSame($passwordHash, $result->passwordHash());
        self::assertInstanceOf(DateTimeImmutable::class, $result->registeredAt());
    }

    public function test_it_does_not_create_a_user_when_the_email_is_already_registered(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())
            ->method('existsByEmail')
            ->with('alice@example.com')
            ->willReturn(true);

        $users->expects(self::never())->method('save');

        $passwordHasher = $this->createMock(PasswordHasher::class);
        $passwordHasher->expects(self::never())->method('hash');

        $userIds = $this->createMock(UserIdGenerator::class);
        $userIds->expects(self::never())->method('generate');

        $registerUser = new RegisterUser($users, $passwordHasher, $userIds);

        $this->expectException(EmailAlreadyRegistered::class);

        $registerUser->execute(new RegisterUserCommand(
            name: 'Alice Example',
            email: 'alice@example.com',
            plainPassword: 'plain-password',
        ));
    }
}
