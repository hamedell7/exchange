<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Commands\RegisterUserCommand;
use App\Modules\Identity\Application\Contracts\PasswordHasher;
use App\Modules\Identity\Application\Contracts\UserIdGenerator;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\UseCases\RegisterUser;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\PasswordHash;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use DateTimeImmutable;
use InvalidArgumentException;
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
        $persistedUser = null;

        $users->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static function (User $user) use (&$persistedUser): bool {
                    $persistedUser = $user;

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

        $user = $registerUser->execute(new RegisterUserCommand(
            name: '  Alice Example  ',
            email: ' Alice@example.com ',
            plainPassword: 'plain-password',
        ));

        self::assertInstanceOf(User::class, $user);
        self::assertSame($userId->value(), $user->id()->value());
        self::assertSame('Alice Example', $user->name());
        self::assertSame('Alice@example.com', $user->email()->value());
        self::assertInstanceOf(DateTimeImmutable::class, $user->registeredAt());
        self::assertSame($passwordHash, $persistedUser->passwordHash());
    }

    public function test_it_rejects_a_short_password_before_using_infrastructure(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::never())->method('save');

        $passwordHasher = $this->createMock(PasswordHasher::class);
        $passwordHasher->expects(self::never())->method('hash');

        $userIds = $this->createMock(UserIdGenerator::class);
        $userIds->expects(self::never())->method('generate');

        $registerUser = new RegisterUser($users, $passwordHasher, $userIds);

        $this->expectException(InvalidArgumentException::class);

        $registerUser->execute(new RegisterUserCommand(
            name: 'Alice Example',
            email: 'alice@example.com',
            plainPassword: 'short',
        ));
    }
}
