<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_account_can_sign_in_without_email_verification(): void
    {
        $password = 'correct-horse-battery-staple';
        $user = User::factory()->unverified()->create([
            'email' => 'Alice@example.com',
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/v1/identity/login', [
            'email' => 'alice@EXAMPLE.com',
            'password' => $password,
            'device_name' => 'Test Browser',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->getKey())
            ->assertJsonPath('data.user.status', 'pending_verification')
            ->assertJsonStructure([
                'data' => ['token_type', 'access_token', 'expires_at', 'user'],
            ]);

        $plainToken = $response->json('data.access_token');
        self::assertIsString($plainToken);
        [$tokenId, $secret] = explode('|', $plainToken, 2);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'tokenable_id' => $user->getKey(),
            'name' => 'Test Browser',
            'token' => hash('sha256', $secret),
        ]);

        $this->withToken($plainToken)
            ->getJson('/api/v1/identity/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->getKey())
            ->assertJsonPath('data.email', 'Alice@example.com');
    }

    public function test_login_rejects_invalid_credentials_with_the_same_generic_response(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        $wrongPassword = $this->postJson('/api/v1/identity/login', [
            'email' => 'alice@example.com',
            'password' => 'wrong-password',
        ]);

        $unknownEmail = $this->postJson('/api/v1/identity/login', [
            'email' => 'unknown@example.com',
            'password' => 'wrong-password',
        ]);

        $wrongPassword
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'The provided credentials are incorrect.']);

        $unknownEmail
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'The provided credentials are incorrect.']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
        ]);
    }

    public function test_suspended_and_closed_accounts_cannot_sign_in(): void
    {
        foreach (['suspended', 'closed'] as $status) {
            $user = User::factory()->create([
                'email' => $status.'@example.com',
                'password' => Hash::make('correct-horse-battery-staple'),
                'status' => $status,
            ]);

            $this->postJson('/api/v1/identity/login', [
                'email' => $user->email,
                'password' => 'correct-horse-battery-staple',
            ])->assertUnauthorized();

            $this->assertDatabaseMissing('personal_access_tokens', [
                'tokenable_id' => $user->getKey(),
            ]);
        }
    }

    public function test_logout_revokes_only_the_current_access_token(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        $login = $this->postJson('/api/v1/identity/login', [
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery-staple',
            'device_name' => 'Browser',
        ])->assertOk();

        $accessToken = $login->json('data.access_token');

        $this->withToken($accessToken)
            ->postJson('/api/v1/identity/logout')
            ->assertOk()
            ->assertJsonPath('message', 'You have been signed out.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
        ]);

        // A real HTTP request starts with a fresh guard. Clear Laravel's
        // memoized RequestGuard between requests in this multi-request test.
        $this->app['auth']->forgetGuards();

        $this->withToken($accessToken)
            ->getJson('/api/v1/identity/me')
            ->assertUnauthorized();
    }

    public function test_authenticated_endpoints_require_a_bearer_token(): void
    {
        $this->getJson('/api/v1/identity/me')->assertUnauthorized();
        $this->postJson('/api/v1/identity/logout')->assertUnauthorized();
    }

    public function test_login_rejects_unrecognized_request_fields(): void
    {
        $this->postJson('/api/v1/identity/login', [
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery-staple',
            'is_admin' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_admin');
    }

    public function test_access_token_expires_at_the_configured_time(): void
    {
        config()->set('identity.access_token_lifetime_minutes', 1);

        User::factory()->create([
            'email' => 'expiry@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        $login = $this->postJson('/api/v1/identity/login', [
            'email' => 'expiry@example.com',
            'password' => 'correct-horse-battery-staple',
        ])->assertOk();

        $accessToken = $login->json('data.access_token');
        self::assertIsString($accessToken);

        $this->withToken($accessToken)
            ->getJson('/api/v1/identity/me')
            ->assertOk();

        $this->travel(2)->minutes();

        // Sanctum's RequestGuard memoizes the authenticated user between
        // requests in this test process; a fresh HTTP request gets a fresh guard.
        $this->app['auth']->forgetGuards();

        $this->withToken($accessToken)
            ->getJson('/api/v1/identity/me')
            ->assertUnauthorized();
    }

    public function test_login_is_rate_limited_per_normalized_email(): void
    {
        $payload = [
            'email' => 'RateLimit@example.com',
            'password' => 'wrong-password',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/identity/login', $payload)->assertUnauthorized();
        }

        $this->postJson('/api/v1/identity/login', [
            ...$payload,
            'email' => 'ratelimit@EXAMPLE.com',
        ])->assertTooManyRequests();
    }
}
