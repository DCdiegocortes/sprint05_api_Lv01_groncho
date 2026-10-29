<?php

namespace Tests\Feature\Exchanges;

use App\Enums\ExchangeStatus;
use App\Enums\ItemStatus;
use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ExchangeItemLockTest extends TestCase
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

    private function pendingExchange(User $requester, User $owner, string $type = 'GIFT', ?Item $requestedItem = null): Exchange
    {
        $match = MatchModel::factory()->create([
            'user_one_id' => $requester->id,
            'user_two_id' => $owner->id,
        ]);
        $requestedItem ??= Item::factory()->create(['user_id' => $owner->id]);
        $offeredItem = $type === 'TRADE' ? Item::factory()->create(['user_id' => $requester->id]) : null;

        return Exchange::factory()->create([
            'match_id' => $match->id,
            'requester_id' => $requester->id,
            'requested_item_id' => $requestedItem->id,
            'offered_item_id' => $offeredItem?->id,
            'type' => $type,
            'status' => ExchangeStatus::PENDING,
        ]);
    }

    private function respond(User $user, Exchange $exchange, string $status)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->putJson("/api/exchanges/{$exchange->id}", ['status' => $status]);
    }

    public function test_accepting_a_gift_locks_the_requested_item(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->pendingExchange($requester, $owner);

        $this->respond($owner, $exchange, 'ACCEPTED')->assertStatus(200);

        $this->assertDatabaseHas('items', [
            'id' => $exchange->requested_item_id,
            'status' => ItemStatus::UNAVAILABLE->value,
        ]);
    }

    public function test_accepting_a_trade_locks_both_items(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->pendingExchange($requester, $owner, 'TRADE');

        $this->respond($owner, $exchange, 'ACCEPTED')->assertStatus(200);

        $this->assertDatabaseHas('items', [
            'id' => $exchange->requested_item_id,
            'status' => ItemStatus::UNAVAILABLE->value,
        ]);
        $this->assertDatabaseHas('items', [
            'id' => $exchange->offered_item_id,
            'status' => ItemStatus::UNAVAILABLE->value,
        ]);
    }

    public function test_rejecting_a_pending_exchange_does_not_lock_the_item(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->pendingExchange($requester, $owner);

        $this->respond($owner, $exchange, 'REJECTED')->assertStatus(200);

        $this->assertDatabaseHas('items', [
            'id' => $exchange->requested_item_id,
            'status' => ItemStatus::AVAILABLE->value,
        ]);
    }

    public function test_accepting_rejects_other_pending_requests_for_the_same_item(): void
    {
        $owner = User::factory()->create();
        $firstRequester = User::factory()->create();
        $secondRequester = User::factory()->create();
        $sharedItem = Item::factory()->create(['user_id' => $owner->id]);

        $winning = $this->pendingExchange($firstRequester, $owner, 'GIFT', $sharedItem);
        $competing = $this->pendingExchange($secondRequester, $owner, 'GIFT', $sharedItem);

        $this->respond($owner, $winning, 'ACCEPTED')->assertStatus(200);

        $this->assertDatabaseHas('exchanges', [
            'id' => $competing->id,
            'status' => ExchangeStatus::REJECTED->value,
        ]);
    }

    public function test_accepting_does_not_touch_pending_requests_for_other_items(): void
    {
        $owner = User::factory()->create();
        $firstRequester = User::factory()->create();
        $secondRequester = User::factory()->create();

        $accepted = $this->pendingExchange($firstRequester, $owner);
        $unrelated = $this->pendingExchange($secondRequester, $owner);

        $this->respond($owner, $accepted, 'ACCEPTED')->assertStatus(200);

        $this->assertDatabaseHas('exchanges', [
            'id' => $unrelated->id,
            'status' => ExchangeStatus::PENDING->value,
        ]);
    }

    public function test_a_locked_item_cannot_be_requested_again(): void
    {
        $owner = User::factory()->create();
        $firstRequester = User::factory()->create();
        $secondRequester = User::factory()->create();
        $sharedItem = Item::factory()->create(['user_id' => $owner->id]);

        $accepted = $this->pendingExchange($firstRequester, $owner, 'GIFT', $sharedItem);
        $secondMatch = MatchModel::factory()->create([
            'user_one_id' => $secondRequester->id,
            'user_two_id' => $owner->id,
        ]);

        $this->respond($owner, $accepted, 'ACCEPTED')->assertStatus(200);

        Auth::forgetGuards();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($secondRequester))
            ->postJson('/api/exchanges', [
                'match_id' => $secondMatch->id,
                'requested_item_id' => $sharedItem->id,
                'type' => 'GIFT',
            ]);

        $response->assertStatus(422);
    }

    public function test_cancelling_an_accepted_exchange_releases_the_items(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->pendingExchange($requester, $owner, 'TRADE');

        $this->respond($owner, $exchange, 'ACCEPTED')->assertStatus(200);
        $this->assertDatabaseHas('items', [
            'id' => $exchange->requested_item_id,
            'status' => ItemStatus::UNAVAILABLE->value,
        ]);

        Auth::forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->deleteJson("/api/exchanges/{$exchange->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('items', [
            'id' => $exchange->requested_item_id,
            'status' => ItemStatus::AVAILABLE->value,
        ]);
        $this->assertDatabaseHas('items', [
            'id' => $exchange->offered_item_id,
            'status' => ItemStatus::AVAILABLE->value,
        ]);
    }

    public function test_finishing_an_exchange_transfers_the_item_and_makes_it_available_again(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->pendingExchange($requester, $owner);

        $this->respond($owner, $exchange, 'ACCEPTED')->assertStatus(200);
        $this->assertDatabaseHas('items', [
            'id' => $exchange->requested_item_id,
            'status' => ItemStatus::UNAVAILABLE->value,
        ]);

        $this->respond($owner, $exchange, 'FINISHED')->assertStatus(200);

        $this->assertDatabaseHas('items', [
            'id' => $exchange->requested_item_id,
            'user_id' => $requester->id,
            'status' => ItemStatus::AVAILABLE->value,
        ]);
    }
}
