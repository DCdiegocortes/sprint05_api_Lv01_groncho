<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ProfileTest extends TestCase
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

    public function test_authenticated_user_can_view_their_profile(): void
    {
        $user = User::factory()->create(['name' => 'Diego Cortes']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonFragment(['email' => $user->email]);
    }

    public function test_viewing_profile_requires_authentication(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_update_their_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->putJson('/api/profile', ['name' => 'Nuevo Nombre']);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nuevo Nombre',
        ]);
    }

    public function test_updating_profile_requires_authentication(): void
    {
        $response = $this->putJson('/api/profile', ['name' => 'Nuevo Nombre']);

        $response->assertStatus(401);
    }

    public function test_profile_update_fails_with_invalid_email(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->putJson('/api/profile', ['email' => 'not-an-email']);

        $response->assertStatus(422);
    }

    public function test_user_cannot_change_their_own_role_via_profile_update(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->putJson('/api/profile', ['role' => 'admin']);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'user',
        ]);
    }

    public function test_user_cannot_view_another_users_profile_data(): void
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($userOne))
            ->getJson('/api/profile');

        $response->assertJsonMissing(['email' => $userTwo->email]);
    }
}
