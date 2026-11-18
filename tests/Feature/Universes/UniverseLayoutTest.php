<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseLayoutTest extends TestCase
{
    use RefreshDatabase;

    private const LAYOUTS = [
        'MASONRY',
        'ACCORDION',
        'CAROUSEL',
        'ROTUNDA',
        'FLOATING',
        'IMAGE_GRID',
        'INFINITE',
        'LIQUID_GLASS',
        'SCROLL_BULGE',
        'SLIDER',
    ];

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

    private function updateLayout(User $user, Universe $universe, array $data)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->putJson("/api/universes/{$universe->id}", $data);
    }

    public function test_a_new_universe_starts_with_the_masonry_layout(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/universes', ['name' => 'My Universe']);

        $response->assertStatus(201);
        $response->assertJsonPath('layout', 'MASONRY');
        $this->assertDatabaseHas('universes', ['user_id' => $user->id, 'layout' => 'MASONRY']);
    }

    public function test_the_owner_can_choose_any_of_the_ten_layouts(): void
    {
        $user = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $user->id]);

        foreach (self::LAYOUTS as $layout) {
            $response = $this->updateLayout($user, $universe, ['layout' => $layout]);

            $response->assertStatus(200);
            $response->assertJsonPath('layout', $layout);
        }
    }

    public function test_an_unknown_layout_is_rejected(): void
    {
        $user = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $user->id]);

        $response = $this->updateLayout($user, $universe, ['layout' => 'PINBOARD']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('layout');
    }

    public function test_updating_other_fields_keeps_the_layout(): void
    {
        $user = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $user->id, 'layout' => 'CAROUSEL']);

        $response = $this->updateLayout($user, $universe, ['name' => 'Renamed']);

        $response->assertStatus(200);
        $response->assertJsonPath('layout', 'CAROUSEL');
    }

    public function test_another_user_cannot_change_the_layout(): void
    {
        $universe = Universe::factory()->create();

        $response = $this->updateLayout(User::factory()->create(), $universe, ['layout' => 'SLIDER']);

        $response->assertStatus(403);
        $this->assertDatabaseHas('universes', ['id' => $universe->id, 'layout' => 'MASONRY']);
    }

    public function test_viewing_a_universe_shows_its_layout(): void
    {
        $universe = Universe::factory()->create(['layout' => 'ROTUNDA']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor(User::factory()->create()))
            ->getJson("/api/universes/{$universe->id}");

        $response->assertJsonPath('layout', 'ROTUNDA');
    }
}
