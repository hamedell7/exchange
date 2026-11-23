<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\Enums\UserStatus;
use App\Modules\Identity\Domain\Policies\PasswordPolicy;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use App\Modules\Identity\Domain\ValueObjects\PasswordHash;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserRegistrationTest extends TestCase
{
    public function test_email_address_preserves_display_casing_and_compares_identity_case_insensitively(): void
    {
        $email = new EmailAddress('  Alice.Example@Example.COM  ');
        $sameIdentity = new EmailAddress('alice.example@example.com');

        self::assertSame('Alice.Example@example.com', $email->value());
        self::assertSame('alice.example@example.com', $email->normalizedForIdentity());
        self::assertTrue($email->equals($sameIdentity));
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
        $hash = PasswordHash::fromEncodedHash(password_hash('a test passphrase', PASSWORD_DEFAULT));

        self::assertNotSame('', $hash->value());

        $this->expectException(InvalidArgumentException::class);

        PasswordHash::fromEncodedHash('plain-text-password');
    }

    public function test_password_policy_accepts_minimum_and_maximum_unicode_lengths(): void
    {
        PasswordPolicy::validate(str_repeat('پ', PasswordPolicy::MIN_LENGTH));
        PasswordPolicy::validate(str_repeat('پ', PasswordPolicy::MAX_LENGTH));

    }

    public function test_password_policy_rejects_too_short_passwords(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PasswordPolicy::validate(str_repeat('پ', PasswordPolicy::MIN_LENGTH - 1));
    }

    public function test_password_policy_rejects_too_long_passwords(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PasswordPolicy::validate(str_repeat('پ', PasswordPolicy::MAX_LENGTH + 1));
    }

    public function test_user_rejects_names_shorter_than_the_domain_minimum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        User::register(
            new UserId('user-123'),
            'A',
            new EmailAddress('alice@example.com'),
            $this->passwordHash(),
            new DateTimeImmutable('2026-11-21T09:00:00+00:00'),
        );
    }

    public function test_user_rejects_names_longer_than_the_domain_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        User::register(
            new UserId('user-123'),
            str_repeat('A', User::MAX_NAME_LENGTH + 1),
            new EmailAddress('alice@example.com'),
            $this->passwordHash(),
            new DateTimeImmutable('2026-11-21T09:00:00+00:00'),
        );
    }

    public function test_registration_creates_an_unverified_pending_account(): void
    {
        $registeredAt = new DateTimeImmutable('2026-11-21T09:00:00+03:30');
        $id = new UserId('user-123');
        $email = new EmailAddress('alice@example.com');
        $passwordHash = $this->passwordHash();

        $user = User::register($id, ' Alice Example ', $email, $passwordHash, $registeredAt);

        self::assertTrue($id->equals($user->id()));
        self::assertSame('Alice Example', $user->name());
        self::assertTrue($email->equals($user->email()));
        self::assertSame($passwordHash, $user->passwordHash());
        self::assertSame($registeredAt, $user->registeredAt());
        self::assertSame(UserStatus::PendingVerification, $user->status());
        self::assertNull($user->emailVerifiedAt());
    }

    public function test_verifying_email_activates_the_account_once(): void
    {
        $user = $this->pendingUser();
        $verifiedAt = new DateTimeImmutable('2026-11-23T10:00:00+00:00');

        $user->verifyEmail($verifiedAt);

        self::assertSame(UserStatus::Active, $user->status());
        self::assertSame($verifiedAt, $user->emailVerifiedAt());

        $this->expectException(DomainException::class);
        $user->verifyEmail($verifiedAt);
    }

    public function test_suspend_reinstate_and_close_follow_allowed_transitions(): void
    {
        $user = $this->pendingUser();
        $user->verifyEmail(new DateTimeImmutable('2026-11-23T10:00:00+00:00'));

        $user->suspend();
        self::assertSame(UserStatus::Suspended, $user->status());

        $user->reinstate();
        self::assertSame(UserStatus::Active, $user->status());

        $user->close();
        self::assertSame(UserStatus::Closed, $user->status());

        $this->expectException(DomainException::class);
        $user->reinstate();
    }

    private function pendingUser(): User
    {
        return User::register(
            new UserId('user-123'),
            'Alice Example',
            new EmailAddress('alice@example.com'),
            $this->passwordHash(),
            new DateTimeImmutable('2026-11-21T09:00:00+00:00'),
        );
    }

    private function passwordHash(): PasswordHash
    {
        return PasswordHash::fromEncodedHash(
            password_hash('test-password-is-long-enough', PASSWORD_DEFAULT),
        );
    }
}
