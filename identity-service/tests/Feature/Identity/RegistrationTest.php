<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_through_the_identity_api(): void
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
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'registered_at']]);

        $this->assertDatabaseHas('users', ['email' => 'Alice@example.com']);
        $user = UserModel::query()->where('email', 'Alice@example.com')->firstOrFail();

        self::assertTrue(Hash::check('correct-horse-battery-staple', $user->password));
        self::assertSame(26, strlen($user->id));
        self::assertArrayNotHasKey('password', $response->json('data'));
    }

    public function test_registration_rejects_an_already_registered_email(): void
    {
        UserModel::factory()->create(['email' => 'alice@example.com']);

        $this->postJson('/api/v1/identity/register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_a_confirmed_strong_password(): void
    {
        $this->postJson('/api/v1/identity/register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }
}
