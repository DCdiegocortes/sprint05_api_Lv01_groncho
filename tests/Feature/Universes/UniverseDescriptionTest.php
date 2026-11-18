<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseDescriptionTest extends TestCase
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

    public function test_a_universe_can_be_created_with_a_description(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/universes', [
                'name' => 'My Universe',
                'description' => 'Chrome, kendo and quiet armour.',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('description', 'Chrome, kendo and quiet armour.');
        $this->assertDatabaseHas('universes', [
            'user_id' => $user->id,
            'description' => 'Chrome, kendo and quiet armour.',
        ]);
    }

    public function test_the_description_is_optional_when_creating_a_universe(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/universes', ['name' => 'My Universe']);

        $response->assertStatus(201);
        $response->assertJsonPath('description', null);
    }

    public function test_a_description_of_255_characters_is_accepted(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/universes', [
                'name' => 'My Universe',
                'description' => str_repeat('a', 255),
            ]);

        $response->assertStatus(201);
    }

    public function test_a_description_over_255_characters_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/universes', [
                'name' => 'My Universe',
                'description' => str_repeat('a', 256),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('description');
    }

    public function test_the_owner_can_update_the_description(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/universes/{$universe->id}", ['description' => 'A new description']);

        $response->assertStatus(200);
        $response->assertJsonPath('description', 'A new description');
        $this->assertDatabaseHas('universes', ['id' => $universe->id, 'description' => 'A new description']);
    }

    public function test_the_owner_can_remove_the_description(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id, 'description' => 'To remove']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/universes/{$universe->id}", ['description' => null]);

        $response->assertStatus(200);
        $response->assertJsonPath('description', null);
    }

    public function test_updating_only_the_name_keeps_the_description(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id, 'description' => 'Keep me']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/universes/{$universe->id}", ['name' => 'Renamed']);

        $response->assertStatus(200);
        $response->assertJsonPath('description', 'Keep me');
    }

    public function test_viewing_a_universe_includes_its_description(): void
    {
        $user = User::factory()->create();
        $universe = Universe::factory()->create(['description' => 'Visible to everyone']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson("/api/universes/{$universe->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('description', 'Visible to everyone');
    }
}
