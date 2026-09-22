<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UsersAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test-token')->accessToken;
    }

    public function test_admin_can_list_all_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'user']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/users');

        $response->assertStatus(200);
        $response->assertJsonCount(4);
    }

    public function test_regular_user_cannot_list_users(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_listing_users_requires_authentication(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
    }

    public function test_admin_can_view_a_specific_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson("/api/users/{$target->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['email' => $target->email]);
    }

    public function test_regular_user_cannot_view_a_specific_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $target = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson("/api/users/{$target->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->deleteJson("/api/users/{$target->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_regular_user_cannot_delete_a_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $target = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->deleteJson("/api/users/{$target->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }
}
