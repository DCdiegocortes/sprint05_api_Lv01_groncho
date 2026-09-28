<?php

namespace Tests\Feature\Rankings;

use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ItemRankingTest extends TestCase
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

    private function requestItem(Item $item, string $status = 'PENDING'): Exchange
    {
        $requester = User::factory()->create();
        $match = MatchModel::factory()->create([
            'user_one_id' => $requester->id,
            'user_two_id' => $item->user_id,
        ]);

        return Exchange::factory()->create([
            'match_id' => $match->id,
            'requester_id' => $requester->id,
            'requested_item_id' => $item->id,
            'offered_item_id' => null,
            'type' => 'GIFT',
            'status' => $status,
        ]);
    }

    public function test_items_ranking_orders_by_request_count_descending(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();

        $popularItem = Item::factory()->create(['user_id' => $owner->id]);
        $this->requestItem($popularItem, 'REJECTED');
        $this->requestItem($popularItem, 'FINISHED');

        $lessPopularItem = Item::factory()->create(['user_id' => $owner->id]);
        $this->requestItem($lessPopularItem, 'PENDING');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($viewer))
            ->getJson('/api/items/ranking');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals($popularItem->id, $data[0]['item']['id']);
        $this->assertEquals(2, $data[0]['request_count']);
        $this->assertEquals($lessPopularItem->id, $data[1]['item']['id']);
        $this->assertEquals(1, $data[1]['request_count']);
    }

    public function test_items_never_requested_are_excluded_from_ranking(): void
    {
        $viewer = User::factory()->create();
        Item::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($viewer))
            ->getJson('/api/items/ranking');

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    }

    public function test_items_ranking_requires_authentication(): void
    {
        $response = $this->getJson('/api/items/ranking');

        $response->assertStatus(401);
    }
}
