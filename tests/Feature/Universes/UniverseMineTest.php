<?php

namespace Tests\Feature\Universes;

use App\Enums\ExchangeStatus;
use App\Enums\ItemStatus;
use App\Enums\MatchStatus;
use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\Universe;
use App\Models\UniverseImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseMineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );
    }

    private function mineFor(User $user)
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->accessToken)
            ->getJson('/api/universes/mine');
    }

    private function userWithUniverse(): User
    {
        $user = User::factory()->create();
        Universe::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    public function test_my_universe_requires_authentication(): void
    {
        $this->getJson('/api/universes/mine')->assertStatus(401);
    }

    public function test_my_universe_is_not_found_when_i_have_not_created_one(): void
    {
        $user = User::factory()->create();
        Universe::factory()->create();

        $this->mineFor($user)->assertStatus(404);
    }

    public function test_i_get_my_own_universe_with_its_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Universe::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $user->id, 'name' => 'Soft Minimal']);
        $image = UniverseImage::factory()->create(['universe_id' => $universe->id]);

        $response = $this->mineFor($user);

        $response->assertStatus(200);
        $response->assertJsonPath('id', $universe->id);
        $response->assertJsonPath('name', 'Soft Minimal');
        $response->assertJsonPath('images.0.url', Storage::disk('public')->url($image->path));
    }

    public function test_the_counters_are_zero_when_i_have_nothing_yet(): void
    {
        $user = $this->userWithUniverse();

        $this->mineFor($user)->assertJsonPath('counts', [
            'pieces' => 0,
            'matches' => 0,
            'requests' => 0,
        ]);
    }

    public function test_pieces_counts_all_my_items_including_the_ones_in_an_exchange(): void
    {
        $user = $this->userWithUniverse();
        Item::factory()->count(2)->create(['user_id' => $user->id]);
        Item::factory()->create(['user_id' => $user->id, 'status' => ItemStatus::UNAVAILABLE]);
        Item::factory()->create();

        $this->mineFor($user)->assertJsonPath('counts.pieces', 3);
    }

    public function test_matches_counts_only_my_active_matches_on_either_side(): void
    {
        $user = $this->userWithUniverse();
        MatchModel::factory()->create(['user_one_id' => $user->id]);
        MatchModel::factory()->create(['user_two_id' => $user->id]);
        MatchModel::factory()->create(['user_one_id' => $user->id, 'status' => MatchStatus::CLOSED]);
        MatchModel::factory()->create();

        $this->mineFor($user)->assertJsonPath('counts.matches', 2);
    }

    public function test_requests_counts_only_pending_exchanges_waiting_for_my_answer(): void
    {
        $user = $this->userWithUniverse();
        $partner = User::factory()->create();
        $match = MatchModel::factory()->create(['user_one_id' => $user->id, 'user_two_id' => $partner->id]);

        Exchange::factory()->create(['match_id' => $match->id, 'requester_id' => $partner->id]);
        Exchange::factory()->create(['match_id' => $match->id, 'requester_id' => $partner->id]);
        Exchange::factory()->create(['match_id' => $match->id, 'requester_id' => $partner->id, 'status' => ExchangeStatus::ACCEPTED]);
        Exchange::factory()->create(['match_id' => $match->id, 'requester_id' => $user->id]);
        Exchange::factory()->create();

        $this->mineFor($user)->assertJsonPath('counts.requests', 2);
    }

    public function test_viewing_a_universe_by_id_is_unchanged(): void
    {
        $viewer = User::factory()->create();
        $universe = Universe::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$viewer->createToken('test-token')->accessToken)
            ->getJson("/api/universes/{$universe->id}")
            ->assertStatus(200)
            ->assertJsonMissingPath('counts');
    }
}
