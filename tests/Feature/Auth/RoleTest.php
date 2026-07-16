<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_all_application_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $expected = ['visitor', 'hotel_manager', 'ferry_operator', 'themepark_staff', 'admin'];

        $this->assertEqualsCanonicalizing(
            $expected,
            Role::pluck('name')->all()
        );
    }

    public function test_new_users_are_assigned_the_visitor_role(): void
    {
        $this->seed(RoleSeeder::class);

        $response = $this->postJson('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.roles.0.name', 'visitor');

        $this->assertTrue(User::where('email', 'test@example.com')->first()->hasRole('visitor'));
    }

    public function test_api_user_includes_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('hotel_manager');

        $response = $this->actingAs($user)->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('roles.0.name', 'hotel_manager');
    }
}
