<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Application;

use App\Modules\Identity\Application\Commands\RegisterUserCommand;
use App\Modules\Identity\Application\Contracts\AccountActionTokenRepository;
use App\Modules\Identity\Application\Contracts\Clock;
use App\Modules\Identity\Application\Contracts\IdentityMailer;
use App\Modules\Identity\Application\Contracts\PasswordHasher;
use App\Modules\Identity\Application\Contracts\TokenGenerator;
use App\Modules\Identity\Application\Contracts\TransactionRunner;
use App\Modules\Identity\Application\Contracts\UserIdGenerator;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Exceptions\InvalidRegistrationInput;
use App\Modules\Identity\Application\UseCases\RegisterUser;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\Enums\UserStatus;
use App\Modules\Identity\Domain\Policies\PasswordPolicy;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use App\Modules\Identity\Domain\ValueObjects\PasswordHash;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RegisterUserTest extends TestCase
{
    public function test_it_registers_a_pending_user_and_issues_a_verification_token(): void
    {
        $plainPassword = 'plain-password-long-enough';
        $now = new DateTimeImmutable('2026-11-23T10:00:00+00:00');
        $userId = new UserId('01JCS7ZJ4M6P7W8X9Y0Z1A2B3C');
        $passwordHash = PasswordHash::fromEncodedHash(
            password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 4]),
        );

        $users = $this->createMock(UserRepository::class);
        $persistedUser = null;
        $users->expects(self::once())
            ->method('add')
            ->with(self::callback(static function (User $user) use (&$persistedUser): bool {
                $persistedUser = $user;

                return true;
            }));

        $passwordHasher = $this->createMock(PasswordHasher::class);
        $passwordHasher->expects(self::once())
            ->method('hash')
            ->with($plainPassword)
            ->willReturn($passwordHash);

        $userIds = $this->createMock(UserIdGenerator::class);
        $userIds->expects(self::once())->method('generate')->willReturn($userId);

        $tokens = $this->createMock(AccountActionTokenRepository::class);
        $tokens->expects(self::once())->method('issue');

        $tokenGenerator = $this->createMock(TokenGenerator::class);
        $tokenGenerator->expects(self::once())->method('generate')->willReturn(str_repeat('a', 64));

        $mailer = $this->createMock(IdentityMailer::class);
        $mailer->expects(self::once())
            ->method('sendEmailVerification')
            ->with(self::callback(fn (EmailAddress $email): bool => $email->value() === 'Alice@example.com'), str_repeat('a', 64));

        $clock = $this->createMock(Clock::class);
        $clock->method('now')->willReturn($now);

        $transactions = $this->createMock(TransactionRunner::class);
        $transactions->method('run')->willReturnCallback(static fn (callable $operation): mixed => $operation());

        $registerUser = new RegisterUser(
            $users,
            $passwordHasher,
            $userIds,
            $tokens,
            $tokenGenerator,
            $mailer,
            $clock,
            $transactions,
        );

        $user = $registerUser->execute(new RegisterUserCommand(
            name: '  Alice Example  ',
            email: ' Alice@example.com ',
            plainPassword: $plainPassword,
        ));

        self::assertInstanceOf(User::class, $user);
        self::assertSame($userId->value(), $user->id()->value());
        self::assertSame('Alice Example', $user->name());
        self::assertSame('Alice@example.com', $user->email()->value());
        self::assertSame(UserStatus::PendingVerification, $user->status());
        self::assertNull($user->emailVerifiedAt());
        self::assertSame($now, $user->registeredAt());
        self::assertSame($passwordHash, $persistedUser->passwordHash());
    }

    public function test_it_rejects_a_short_password_before_using_infrastructure(): void
    {
        [$registerUser, $users, $passwordHasher, $tokens, $mailer] = $this->makeUseCase();

        $users->expects(self::never())->method('add');
        $passwordHasher->expects(self::never())->method('hash');
        $tokens->expects(self::never())->method('issue');
        $mailer->expects(self::never())->method('sendEmailVerification');

        try {
            $registerUser->execute(new RegisterUserCommand(
                name: 'Alice Example',
                email: 'alice@example.com',
                plainPassword: 'too-short',
            ));

            self::fail('A short password must be rejected.');
        } catch (InvalidRegistrationInput $exception) {
            self::assertSame('password', $exception->field);
        }
    }

    public function test_it_rejects_a_password_over_the_domain_limit_before_using_infrastructure(): void
    {
        [$registerUser, $users, $passwordHasher] = $this->makeUseCase();

        $users->expects(self::never())->method('add');
        $passwordHasher->expects(self::never())->method('hash');

        try {
            $registerUser->execute(new RegisterUserCommand(
                name: 'Alice Example',
                email: 'alice@example.com',
                plainPassword: str_repeat('a', PasswordPolicy::MAX_LENGTH + 1),
            ));

            self::fail('An overlong password must be rejected.');
        } catch (InvalidRegistrationInput $exception) {
            self::assertSame('password', $exception->field);
        }
    }

    public function test_it_rejects_an_invalid_email_before_using_infrastructure(): void
    {
        [$registerUser, $users, $passwordHasher] = $this->makeUseCase();

        $users->expects(self::never())->method('add');
        $passwordHasher->expects(self::never())->method('hash');

        try {
            $registerUser->execute(new RegisterUserCommand(
                name: 'Alice Example',
                email: 'not-an-email',
                plainPassword: 'correct-horse-battery-staple',
            ));

            self::fail('An invalid email must be rejected.');
        } catch (InvalidRegistrationInput $exception) {
            self::assertSame('email', $exception->field);
        }
    }

    public function test_it_rejects_a_name_shorter_than_the_domain_minimum(): void
    {
        [$registerUser, $users, $passwordHasher] = $this->makeUseCase();

        $users->expects(self::never())->method('add');
        $passwordHasher->method('hash')->willReturn(
            PasswordHash::fromEncodedHash(password_hash('correct-horse-battery-staple', PASSWORD_BCRYPT, ['cost' => 4])),
        );

        try {
            $registerUser->execute(new RegisterUserCommand(
                name: 'A',
                email: 'alice@example.com',
                plainPassword: 'correct-horse-battery-staple',
            ));

            self::fail('A name shorter than the domain minimum must be rejected.');
        } catch (InvalidRegistrationInput $exception) {
            self::assertSame('name', $exception->field);
        }
    }

    public function test_it_rejects_a_name_longer_than_the_domain_maximum(): void
    {
        [$registerUser, $users, $passwordHasher] = $this->makeUseCase();

        $users->expects(self::never())->method('add');
        $passwordHasher->method('hash')->willReturn(
            PasswordHash::fromEncodedHash(password_hash('correct-horse-battery-staple', PASSWORD_BCRYPT, ['cost' => 4])),
        );

        try {
            $registerUser->execute(new RegisterUserCommand(
                name: str_repeat('a', User::MAX_NAME_LENGTH + 1),
                email: 'alice@example.com',
                plainPassword: 'correct-horse-battery-staple',
            ));

            self::fail('An overlong name must be rejected.');
        } catch (InvalidRegistrationInput $exception) {
            self::assertSame('name', $exception->field);
        }
    }

    /**
     * @return array{RegisterUser, UserRepository&\PHPUnit\Framework\MockObject\MockObject, PasswordHasher&\PHPUnit\Framework\MockObject\MockObject, AccountActionTokenRepository&\PHPUnit\Framework\MockObject\MockObject, IdentityMailer&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function makeUseCase(): array
    {
        $users = $this->createMock(UserRepository::class);
        $passwordHasher = $this->createMock(PasswordHasher::class);
        $tokens = $this->createMock(AccountActionTokenRepository::class);
        $tokenGenerator = $this->createMock(TokenGenerator::class);
        $tokenGenerator->method('generate')->willReturn(str_repeat('b', 64));
        $mailer = $this->createMock(IdentityMailer::class);
        $clock = $this->createMock(Clock::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-11-23T10:00:00+00:00'));
        $transactions = $this->createMock(TransactionRunner::class);
        $transactions->method('run')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $userIds = $this->createMock(UserIdGenerator::class);
        $userIds->method('generate')->willReturn(new UserId('01JCS7ZJ4M6P7W8X9Y0Z1A2B3C'));

        return [
            new RegisterUser($users, $passwordHasher, $userIds, $tokens, $tokenGenerator, $mailer, $clock, $transactions),
            $users,
            $passwordHasher,
            $tokens,
            $mailer,
        ];
    }
}
