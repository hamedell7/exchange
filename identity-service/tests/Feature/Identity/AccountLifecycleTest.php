<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Mail\Identity\EmailVerificationMail;
use App\Mail\Identity\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_legacy_pending_account_can_still_verify_email_with_a_single_use_token(): void
    {
        User::factory()->unverified()->create([
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
        ]);

        $this->postJson('/api/v1/identity/email/verification/resend', [
            'email' => 'alice@example.com',
        ])->assertAccepted();

        $token = null;
        Mail::assertSent(EmailVerificationMail::class, function (EmailVerificationMail $mail) use (&$token): bool {
            parse_str((string) parse_url($mail->url, PHP_URL_FRAGMENT), $fragment);
            $token = $fragment['token'] ?? null;

            return is_string($token);
        });

        self::assertNotNull($token);
        $this->assertDatabaseHas('identity_action_tokens', [
            'purpose' => 'verify_email',
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('identity_action_tokens', ['token_hash' => $token]);

        $this->postJson('/api/v1/identity/email/verification/verify', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.email', 'alice@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/identity/email/verification/verify', ['token' => $token])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');
    }

    public function test_resending_verification_returns_the_same_response_for_unknown_email(): void
    {
        $this->postJson('/api/v1/identity/email/verification/resend', [
            'email' => 'nobody@example.com',
        ])->assertAccepted();

        Mail::assertNothingSent();
    }

    public function test_password_reset_request_is_generic_and_reset_token_can_only_be_used_once(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('old-password-is-long-enough'),
            'status' => 'active',
        ]);
        $user->createToken('old-device');

        $this->postJson('/api/v1/identity/password/forgot', [
            'email' => 'Alice@EXAMPLE.com',
        ])->assertAccepted();

        $token = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$token): bool {
            parse_str((string) parse_url($mail->url, PHP_URL_FRAGMENT), $fragment);
            $token = $fragment['token'] ?? null;

            return is_string($token);
        });

        self::assertNotNull($token);
        $this->assertDatabaseHas('identity_action_tokens', [
            'purpose' => 'reset_password',
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('identity_action_tokens', ['token_hash' => $token]);

        $newPassword = 'an-even-better-password-2026';

        $this->postJson('/api/v1/identity/password/reset', [
            'token' => $token,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertOk();

        $user->refresh();
        self::assertTrue(Hash::check($newPassword, $user->password));
        $this->assertDatabaseMissing('identity_action_tokens', [
            'purpose' => 'reset_password',
            'consumed_at' => null,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
        ]);

        $this->postJson('/api/v1/identity/password/reset', [
            'token' => $token,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_password_reset_response_does_not_disclose_unknown_accounts(): void
    {
        $response = $this->postJson('/api/v1/identity/password/forgot', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertAccepted()->assertJsonPath(
            'message',
            'If the account exists, password reset instructions will be sent to its email address.',
        );

        Mail::assertNothingSent();
    }

    public function test_reset_request_rejects_unrecognized_fields(): void
    {
        $this->postJson('/api/v1/identity/password/forgot', [
            'email' => 'alice@example.com',
            'send_to' => 'attacker@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors('send_to');
    }
}
