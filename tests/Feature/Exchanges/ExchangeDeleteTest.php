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

class ExchangeDeleteTest extends TestCase
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

    public function test_requester_can_cancel_their_exchange(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->deleteJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('exchanges', ['id' => $exchange->id]);
    }

    public function test_admin_can_cancel_any_exchange(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->deleteJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('exchanges', ['id' => $exchange->id]);
    }

    public function test_receiver_cannot_cancel_exchange(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->deleteJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('exchanges', ['id' => $exchange->id]);
    }

    public function test_non_participant_cannot_cancel_exchange(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->deleteJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(403);
    }

    public function test_cancelling_exchange_requires_authentication(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner);

        $response = $this->deleteJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(401);
    }

    public function test_cannot_cancel_a_finished_exchange(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->giftExchange($requester, $owner, ExchangeStatus::FINISHED);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->deleteJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('exchanges', ['id' => $exchange->id]);
    }
}
