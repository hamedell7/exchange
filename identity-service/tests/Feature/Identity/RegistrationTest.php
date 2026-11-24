<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_user_can_register_and_sign_in_without_email_verification(): void
    {
        $response = $this->postJson('/api/v1/identity/register', [
            'name' => 'Alice Example',
            'email' => 'Alice@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Alice Example')
            ->assertJsonPath('data.email', 'Alice@example.com')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.email_verified_at', null)
            ->assertJsonStructure(['data' => [
                'id', 'name', 'email', 'status', 'email_verified_at', 'registered_at',
            ]]);

        $this->assertDatabaseHas('users', [
            'email' => 'Alice@example.com',
            'email_normalized' => 'alice@example.com',
            'status' => 'active',
            'email_verified_at' => null,
        ]);

        $this->assertDatabaseMissing('identity_action_tokens', [
            'purpose' => 'verify_email',
        ]);

        $user = User::query()->where('email', 'Alice@example.com')->firstOrFail();

        self::assertTrue(Hash::check('correct-horse-battery-staple', $user->password));
        self::assertSame('argon2id', password_get_info($user->password)['algoName']);
        self::assertSame(26, strlen($user->id));
        self::assertArrayNotHasKey('password', $response->json('data'));
        self::assertArrayNotHasKey('email_normalized', $user->toArray());
        Mail::assertNothingSent();

        $this->postJson('/api/v1/identity/login', [
            'email' => 'alice@EXAMPLE.com',
            'password' => 'correct-horse-battery-staple',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    public function test_email_identity_is_case_insensitive_and_duplicate_returns_conflict(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);

        $this->postJson('/api/v1/identity/register', [
            'name' => 'Alice Example',
            'email' => 'Alice@EXAMPLE.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])
            ->assertStatus(409)
            ->assertJsonPath('errors.email.0', 'The email address is already registered.');
    }

    public function test_a_different_unique_constraint_is_not_reported_as_duplicate_email(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.com']);

        User::creating(static function (User $user) use ($existing): void {
            if ($user->email === 'another@example.com') {
                $user->setAttribute($user->getKeyName(), $existing->getKey());
            }
        });

        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        $this->postJson('/api/v1/identity/register', [
            'name' => 'Another User',
            'email' => 'another@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ]);
    }

    public function test_registration_rejects_a_short_password_when_confirmation_matches(): void
    {
        $this->postJson('/api/v1/identity/register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'too-short',
            'password_confirmation' => 'too-short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_registration_rejects_a_password_longer_than_128_characters(): void
    {
        $password = str_repeat('a', 129);

        $this->postJson('/api/v1/identity/register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_registration_rejects_a_mismatched_password_confirmation(): void
    {
        $this->postJson('/api/v1/identity/register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'different-horse-battery-staple',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_registration_rejects_an_invalid_email_address(): void
    {
        $this->postJson('/api/v1/identity/register', [
            'name' => 'Alice Example',
            'email' => 'not-an-email',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_registration_rejects_unrecognized_fields(): void
    {
        $this->postJson('/api/v1/identity/register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
            'is_admin' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_admin');

        $this->assertDatabaseCount('users', 0);
    }
}
