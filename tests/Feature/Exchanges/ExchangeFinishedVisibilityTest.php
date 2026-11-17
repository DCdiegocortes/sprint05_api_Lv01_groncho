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

class ExchangeFinishedVisibilityTest extends TestCase
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

    private function finishedExchange(User $requester, User $originalOwner): Exchange
    {
        $match = MatchModel::factory()->create([
            'user_one_id' => $requester->id,
            'user_two_id' => $originalOwner->id,
        ]);
        $item = Item::factory()->create(['user_id' => $requester->id]);

        return Exchange::factory()->create([
            'match_id' => $match->id,
            'requester_id' => $requester->id,
            'requested_item_id' => $item->id,
            'status' => ExchangeStatus::FINISHED,
        ]);
    }

    public function test_original_owner_can_still_view_a_finished_exchange(): void
    {
        $requester = User::factory()->create();
        $originalOwner = User::factory()->create();
        $exchange = $this->finishedExchange($requester, $originalOwner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($originalOwner))
            ->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('id', $exchange->id);
    }

    public function test_original_owner_still_sees_a_finished_exchange_in_the_list(): void
    {
        $requester = User::factory()->create();
        $originalOwner = User::factory()->create();
        $this->finishedExchange($requester, $originalOwner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($originalOwner))
            ->getJson('/api/exchanges');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_a_user_outside_the_match_cannot_view_a_finished_exchange(): void
    {
        $requester = User::factory()->create();
        $originalOwner = User::factory()->create();
        $intruder = User::factory()->create();
        $exchange = $this->finishedExchange($requester, $originalOwner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(403);
    }
}
