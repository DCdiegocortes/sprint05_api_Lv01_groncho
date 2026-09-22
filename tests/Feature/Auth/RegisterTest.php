<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Diego Cortes',
            'email' => 'diego@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'diego@example.com',
            'role' => 'user',
        ]);
    }

    public function test_register_fails_without_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Diego Cortes',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'diego@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Diego Cortes',
            'email' => 'diego@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_without_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Diego Cortes',
            'email' => 'diego@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_always_creates_user_role_even_if_role_is_sent(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Diego Cortes',
            'email' => 'diego@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'diego@example.com',
            'role' => 'user',
        ]);
    }
}
