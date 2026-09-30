<?php

namespace Tests\Feature\Exchanges;

use App\Enums\ExchangeStatus;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ExchangeCreateTest extends TestCase
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

    private function matchedPair(): array
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $match = MatchModel::factory()->create([
            'user_one_id' => $requester->id,
            'user_two_id' => $owner->id,
        ]);

        return [$requester, $owner, $match];
    }

    public function test_participant_can_request_a_gift_exchange(): void
    {
        [$requester, $owner, $match] = $this->matchedPair();
        $requestedItem = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->postJson('/api/exchanges', [
                'match_id' => $match->id,
                'requested_item_id' => $requestedItem->id,
                'type' => 'GIFT',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('exchanges', [
            'requester_id' => $requester->id,
            'requested_item_id' => $requestedItem->id,
            'type' => 'GIFT',
            'status' => 'PENDING',
        ]);
    }

    public function test_participant_can_request_a_trade_exchange(): void
    {
        [$requester, $owner, $match] = $this->matchedPair();
        $requestedItem = Item::factory()->create(['user_id' => $owner->id]);
        $offeredItem = Item::factory()->create(['user_id' => $requester->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->postJson('/api/exchanges', [
                'match_id' => $match->id,
                'requested_item_id' => $requestedItem->id,
                'offered_item_id' => $offeredItem->id,
                'type' => 'TRADE',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('exchanges', [
            'requester_id' => $requester->id,
            'offered_item_id' => $offeredItem->id,
            'type' => 'TRADE',
        ]);
    }

    public function test_creating_exchange_requires_authentication(): void
    {
        [$requester, $owner, $match] = $this->matchedPair();
        $requestedItem = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->postJson('/api/exchanges', [
            'match_id' => $match->id,
            'requested_item_id' => $requestedItem->id,
            'type' => 'GIFT',
        ]);

        $response->assertStatus(401);
    }

    public function test_requested_item_must_belong_to_the_other_match_participant(): void
    {
        [$requester, $owner, $match] = $this->matchedPair();
        $ownItem = Item::factory()->create(['user_id' => $requester->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->postJson('/api/exchanges', [
                'match_id' => $match->id,
                'requested_item_id' => $ownItem->id,
                'type' => 'GIFT',
            ]);

        $response->assertStatus(422);
    }

    public function test_requested_item_must_belong_to_the_match_partner_not_a_third_party(): void
    {
        [$requester, $owner, $match] = $this->matchedPair();
        $thirdPartyItem = Item::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->postJson('/api/exchanges', [
                'match_id' => $match->id,
                'requested_item_id' => $thirdPartyItem->id,
                'type' => 'GIFT',
            ]);

        $response->assertStatus(422);
    }

    public function test_offered_item_must_belong_to_requester(): void
    {
        [$requester, $owner, $match] = $this->matchedPair();
        $requestedItem = Item::factory()->create(['user_id' => $owner->id]);
        $someoneElsesItem = Item::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->postJson('/api/exchanges', [
                'match_id' => $match->id,
                'requested_item_id' => $requestedItem->id,
                'offered_item_id' => $someoneElsesItem->id,
                'type' => 'TRADE',
            ]);

        $response->assertStatus(422);
    }

    public function test_trade_requires_an_offered_item(): void
    {
        [$requester, $owner, $match] = $this->matchedPair();
        $requestedItem = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->postJson('/api/exchanges', [
                'match_id' => $match->id,
                'requested_item_id' => $requestedItem->id,
                'type' => 'TRADE',
            ]);

        $response->assertStatus(422);
    }

    public function test_gift_cannot_have_an_offered_item(): void
    {
        [$requester, $owner, $match] = $this->matchedPair();
        $requestedItem = Item::factory()->create(['user_id' => $owner->id]);
        $offeredItem = Item::factory()->create(['user_id' => $requester->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->postJson('/api/exchanges', [
                'match_id' => $match->id,
                'requested_item_id' => $requestedItem->id,
                'offered_item_id' => $offeredItem->id,
                'type' => 'GIFT',
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_request_exchange_without_being_part_of_the_match(): void
    {
        [$requester, $owner, $match] = $this->matchedPair();
        $intruder = User::factory()->create();
        $requestedItem = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->postJson('/api/exchanges', [
                'match_id' => $match->id,
                'requested_item_id' => $requestedItem->id,
                'type' => 'GIFT',
            ]);

        $response->assertStatus(422);
    }

    public function test_requested_item_must_be_available(): void
    {
        [$requester, $owner, $match] = $this->matchedPair();
        $requestedItem = Item::factory()->create([
            'user_id' => $owner->id,
            'status' => 'UNAVAILABLE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->postJson('/api/exchanges', [
                'match_id' => $match->id,
                'requested_item_id' => $requestedItem->id,
                'type' => 'GIFT',
            ]);

        $response->assertStatus(422);
    }

    public function test_regla_a_blocks_creation_when_balance_is_too_negative(): void
    {
        [$requester, $owner, $match] = $this->matchedPair();

        // requester already has 3 FINISHED exchanges as requester and 0 as giver
        // (recibidos - dados = 3 > 2), so a new request should be blocked.
        for ($i = 0; $i < 3; $i++) {
            $otherOwner = User::factory()->create();
            $otherMatch = MatchModel::factory()->create([
                'user_one_id' => $requester->id,
                'user_two_id' => $otherOwner->id,
            ]);
            $item = Item::factory()->create(['user_id' => $otherOwner->id]);

            \App\Models\Exchange::factory()->create([
                'match_id' => $otherMatch->id,
                'requester_id' => $requester->id,
                'requested_item_id' => $item->id,
                'offered_item_id' => null,
                'type' => 'GIFT',
                'status' => ExchangeStatus::FINISHED,
            ]);
        }

        $requestedItem = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->postJson('/api/exchanges', [
                'match_id' => $match->id,
                'requested_item_id' => $requestedItem->id,
                'type' => 'GIFT',
            ]);

        $response->assertStatus(403);
    }
}
