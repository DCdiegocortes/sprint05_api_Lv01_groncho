<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseViewTest extends TestCase
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

    public function test_authenticated_user_can_list_all_universes(): void
    {
        $viewer = User::factory()->create();
        Universe::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($viewer))
            ->getJson('/api/universes');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
    }

    public function test_listing_universes_requires_authentication(): void
    {
        $response = $this->getJson('/api/universes');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_view_a_specific_universe(): void
    {
        $viewer = User::factory()->create();
        $universe = Universe::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($viewer))
            ->getJson("/api/universes/{$universe->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $universe->name]);
    }

    public function test_viewing_universe_requires_authentication(): void
    {
        $universe = Universe::factory()->create();

        $response = $this->getJson("/api/universes/{$universe->id}");

        $response->assertStatus(401);
    }

    public function test_viewing_nonexistent_universe_returns_404(): void
    {
        $viewer = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($viewer))
            ->getJson('/api/universes/999');

        $response->assertStatus(404);
    }
}
