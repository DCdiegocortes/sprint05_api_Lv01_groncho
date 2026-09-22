<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseCreateTest extends TestCase
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

    public function test_authenticated_user_can_create_their_own_universe(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/universes', [
                'name' => 'My Universe',
                'style' => 'minimal',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('universes', [
            'user_id' => $user->id,
            'name' => 'My Universe',
        ]);
    }

    public function test_creating_universe_requires_authentication(): void
    {
        $response = $this->postJson('/api/universes', ['name' => 'My Universe']);

        $response->assertStatus(401);
    }

    public function test_creating_universe_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/universes', ['style' => 'minimal']);

        $response->assertStatus(422);
    }

    public function test_user_cannot_create_more_than_one_universe(): void
    {
        $user = User::factory()->create();
        Universe::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/universes', ['name' => 'Second Universe']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('universes', 1);
    }

    public function test_universe_is_always_created_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/universes', [
                'name' => 'My Universe',
                'user_id' => $otherUser->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('universes', [
            'name' => 'My Universe',
            'user_id' => $user->id,
        ]);
    }
}
