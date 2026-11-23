<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ListUsersQueryTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_READ_TOKEN = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.users_read_token', self::ADMIN_READ_TOKEN);
    }

    public function test_user_list_requires_the_server_to_server_token(): void
    {
        $this->getJson('/api/v1/identity/users')
            ->assertUnauthorized();

        $this->getJson('/api/v1/identity/users', [
            'X-Identity-Admin-Token' => str_repeat('x', 64),
        ])->assertUnauthorized();
    }

    public function test_user_list_fails_closed_when_the_token_is_not_configured(): void
    {
        config()->set('identity.users_read_token', null);

        $this->getJson('/api/v1/identity/users', [
            'X-Identity-Admin-Token' => self::ADMIN_READ_TOKEN,
        ])->assertStatus(503);
    }

    public function test_it_returns_a_paginated_allow_list_of_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/identity/users?per_page=2&page=1', [
            'X-Identity-Admin-Token' => self::ADMIN_READ_TOKEN,
        ]);

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);

        self::assertArrayNotHasKey('password', $response->json('data.0'));
        self::assertArrayNotHasKey('remember_token', $response->json('data.0'));
        self::assertArrayNotHasKey('email_normalized', $response->json('data.0'));
    }

    public function test_it_filters_by_case_insensitive_name_or_email_and_status(): void
    {
        User::factory()->create([
            'name' => 'Alice Example',
            'email' => 'Alice@example.com',
            'status' => 'active',
        ]);

        User::factory()->create([
            'name' => 'Bob Example',
            'email' => 'bob@example.com',
            'status' => 'active',
        ]);

        User::factory()->unverified()->create([
            'name' => 'Alice Pending',
            'email' => 'pending-alice@example.com',
        ]);

        $this->getJson('/api/v1/identity/users?search=ALICE&status=active', [
            'X-Identity-Admin-Token' => self::ADMIN_READ_TOKEN,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alice Example')
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_it_rejects_invalid_filters_and_unknown_query_parameters(): void
    {
        $headers = ['X-Identity-Admin-Token' => self::ADMIN_READ_TOKEN];

        $this->getJson('/api/v1/identity/users?status=unknown', $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->getJson('/api/v1/identity/users?is_admin=true', $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_admin');

        $this->getJson('/api/v1/identity/users?per_page=101', $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
}
