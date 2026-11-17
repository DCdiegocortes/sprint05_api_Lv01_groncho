<?php

namespace Tests\Feature\Matches;

use App\Enums\ItemStatus;
use App\Enums\MatchStatus;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class MatchWardrobeTest extends TestCase
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

    private function matchBetween(User $one, User $two, MatchStatus $status = MatchStatus::ACTIVE): MatchModel
    {
        return MatchModel::factory()->create([
            'user_one_id' => $one->id,
            'user_two_id' => $two->id,
            'status' => $status,
        ]);
    }

    public function test_participant_sees_the_available_items_of_their_partner_with_images(): void
    {
        $user = User::factory()->create();
        $partner = User::factory()->create();
        $match = $this->matchBetween($user, $partner);
        $item = Item::factory()->create(['user_id' => $partner->id]);
        ItemImage::factory()->create(['item_id' => $item->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson("/api/matches/{$match->id}/items");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $item->id);
        $response->assertJsonCount(1, '0.images');
    }

    public function test_user_one_sees_the_items_of_user_two(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        $match = $this->matchBetween($one, $two);
        Item::factory()->create(['user_id' => $one->id]);
        $itemOfTwo = Item::factory()->create(['user_id' => $two->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($one))
            ->getJson("/api/matches/{$match->id}/items");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $itemOfTwo->id);
    }

    public function test_user_two_sees_the_items_of_user_one(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        $match = $this->matchBetween($one, $two);
        $itemOfOne = Item::factory()->create(['user_id' => $one->id]);
        Item::factory()->create(['user_id' => $two->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($two))
            ->getJson("/api/matches/{$match->id}/items");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $itemOfOne->id);
    }

    public function test_it_does_not_include_the_items_of_the_requesting_user(): void
    {
        $user = User::factory()->create();
        $partner = User::factory()->create();
        $match = $this->matchBetween($user, $partner);
        Item::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson("/api/matches/{$match->id}/items");

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    }

    public function test_it_does_not_include_unavailable_items(): void
    {
        $user = User::factory()->create();
        $partner = User::factory()->create();
        $match = $this->matchBetween($user, $partner);
        Item::factory()->create(['user_id' => $partner->id, 'status' => ItemStatus::UNAVAILABLE]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson("/api/matches/{$match->id}/items");

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    }

    public function test_it_does_not_include_items_of_other_users(): void
    {
        $user = User::factory()->create();
        $partner = User::factory()->create();
        $match = $this->matchBetween($user, $partner);
        Item::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson("/api/matches/{$match->id}/items");

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    }

    public function test_non_participant_cannot_see_the_wardrobe(): void
    {
        $intruder = User::factory()->create();
        $match = MatchModel::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->getJson("/api/matches/{$match->id}/items");

        $response->assertStatus(403);
    }

    public function test_participant_cannot_see_the_wardrobe_of_a_closed_match(): void
    {
        $user = User::factory()->create();
        $partner = User::factory()->create();
        $match = $this->matchBetween($user, $partner, MatchStatus::CLOSED);
        Item::factory()->create(['user_id' => $partner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson("/api/matches/{$match->id}/items");

        $response->assertStatus(403);
    }

    public function test_wardrobe_requires_authentication(): void
    {
        $match = MatchModel::factory()->create();

        $response = $this->getJson("/api/matches/{$match->id}/items");

        $response->assertStatus(401);
    }

    public function test_wardrobe_of_a_missing_match_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/matches/9999/items');

        $response->assertStatus(404);
    }
}
