<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_can_register(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $this->assertAuthenticated();
        $response->assertCreated()
            ->assertJsonPath('user.email', 'test@example.com');
    }

    public function test_registration_requires_unique_email(): void
    {
        $this->postJson('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $this->postJson('/logout');

        $response = $this->postJson('/register', [
            'name' => 'Another User',
            'email' => 'test@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
