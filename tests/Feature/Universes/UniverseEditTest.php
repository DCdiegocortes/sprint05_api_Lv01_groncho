<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseEditTest extends TestCase
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

    public function test_owner_can_update_their_universe(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/universes/{$universe->id}", ['name' => 'Updated Name']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('universes', ['id' => $universe->id, 'name' => 'Updated Name']);
    }

    public function test_admin_can_update_any_universe(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $universe = Universe::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->putJson("/api/universes/{$universe->id}", ['name' => 'Updated By Admin']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('universes', ['id' => $universe->id, 'name' => 'Updated By Admin']);
    }

    public function test_other_user_cannot_update_someone_elses_universe(): void
    {
        $intruder = User::factory()->create();
        $universe = Universe::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->putJson("/api/universes/{$universe->id}", ['name' => 'Hacked']);

        $response->assertStatus(403);
    }

    public function test_updating_universe_requires_authentication(): void
    {
        $universe = Universe::factory()->create();

        $response = $this->putJson("/api/universes/{$universe->id}", ['name' => 'Nope']);

        $response->assertStatus(401);
    }

    public function test_owner_can_delete_their_universe(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->deleteJson("/api/universes/{$universe->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('universes', ['id' => $universe->id]);
    }

    public function test_admin_can_delete_any_universe(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $universe = Universe::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->deleteJson("/api/universes/{$universe->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('universes', ['id' => $universe->id]);
    }

    public function test_other_user_cannot_delete_someone_elses_universe(): void
    {
        $intruder = User::factory()->create();
        $universe = Universe::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->deleteJson("/api/universes/{$universe->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('universes', ['id' => $universe->id]);
    }

    public function test_deleting_universe_requires_authentication(): void
    {
        $universe = Universe::factory()->create();

        $response = $this->deleteJson("/api/universes/{$universe->id}");

        $response->assertStatus(401);
    }
}
