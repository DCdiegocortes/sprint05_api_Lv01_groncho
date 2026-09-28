<?php

namespace Tests\Feature\Exchanges;

use App\Enums\ExchangeStatus;
use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ExchangeStatusTest extends TestCase
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

    private function giftExchange(User $requester, User $owner, ExchangeStatus $status = ExchangeStatus::PENDING): Exchange
    {
        $match = MatchModel::factory()->create([
            'user_one_id' => $requester->id,
            'user_two_id' => $owner->id,
        ]);
        $item = Item::factory()->create(['user_id' => $owner->id]);

        return Exchange::factory()->create([
            'match_id' => $match->id,
            'requester_id' => $requester->id,
            'requested_item_id' => $item->id,
            'offered_item_id' => null,
            'type' => 'GIFT',
            'status' => $status,
        ]);
    }

    private function tradeExchange(User $requester, User $owner, ExchangeStatus $status = ExchangeStatus::PENDING): Exchange
    {
        $match = MatchModel::factory()->create([
            'user_one_id' => $requester->id,
            'user_two_id' => $owner->id,
        ]);
        $requestedItem = Item::factory()->create(['user_id' => $owner->id]);
        $offeredItem = Item::factory()->create(['user_id' => $requester->id]);

        return Exchange::factory()->create([
            'match_id' => $match->id,
            'requester_id' => $requester->id,
            'requested_item_id' => $requestedItem->id,
            'offered_item_id' => $offeredItem->id,
            'type' => 'TRADE',
            'status' => $status,
        ]);
    }

    public function test_receiver_can_accept_exchange(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/exchanges/{$exchange->id}", ['status' => 'ACCEPTED']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('exchanges', ['id' => $exchange->id, 'status' => 'ACCEPTED']);
    }

    public function test_receiver_can_reject_exchange(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/exchanges/{$exchange->id}", ['status' => 'REJECTED']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('exchanges', ['id' => $exchange->id, 'status' => 'REJECTED']);
    }

    public function test_requester_cannot_change_status(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->putJson("/api/exchanges/{$exchange->id}", ['status' => 'ACCEPTED']);

        $response->assertStatus(403);
    }

    public function test_non_participant_cannot_change_status(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->putJson("/api/exchanges/{$exchange->id}", ['status' => 'ACCEPTED']);

        $response->assertStatus(403);
    }

    public function test_updating_status_requires_authentication(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner);

        $response = $this->putJson("/api/exchanges/{$exchange->id}", ['status' => 'ACCEPTED']);

        $response->assertStatus(401);
    }

    public function test_admin_can_change_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->putJson("/api/exchanges/{$exchange->id}", ['status' => 'ACCEPTED']);

        $response->assertStatus(200);
    }

    public function test_finishing_a_gift_exchange_transfers_item_ownership(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner, ExchangeStatus::ACCEPTED);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/exchanges/{$exchange->id}", ['status' => 'FINISHED']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', [
            'id' => $exchange->requested_item_id,
            'user_id' => $requester->id,
        ]);
    }

    public function test_finishing_a_trade_exchange_swaps_item_ownership(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->tradeExchange($requester, $owner, ExchangeStatus::ACCEPTED);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/exchanges/{$exchange->id}", ['status' => 'FINISHED']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', [
            'id' => $exchange->requested_item_id,
            'user_id' => $requester->id,
        ]);
        $this->assertDatabaseHas('items', [
            'id' => $exchange->offered_item_id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_cannot_finish_a_pending_exchange(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner, ExchangeStatus::PENDING);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/exchanges/{$exchange->id}", ['status' => 'FINISHED']);

        $response->assertStatus(422);
    }

    public function test_cannot_change_status_of_an_already_finished_exchange(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner, ExchangeStatus::FINISHED);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/exchanges/{$exchange->id}", ['status' => 'REJECTED']);

        $response->assertStatus(422);
    }
}
