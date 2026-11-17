<?php

namespace Tests\Feature\Exchanges;

use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ExchangeInvalidStatusTest extends TestCase
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

    private function pendingExchangeFor(User $owner): Exchange
    {
        $requester = User::factory()->create();
        $match = MatchModel::factory()->create([
            'user_one_id' => $requester->id,
            'user_two_id' => $owner->id,
        ]);
        $item = Item::factory()->create(['user_id' => $owner->id]);

        return Exchange::factory()->create([
            'match_id' => $match->id,
            'requester_id' => $requester->id,
            'requested_item_id' => $item->id,
        ]);
    }

    public function test_a_status_that_does_not_exist_returns_422(): void
    {
        $owner = User::factory()->create();
        $exchange = $this->pendingExchangeFor($owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/exchanges/{$exchange->id}", ['status' => 'BANANA']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    public function test_a_missing_status_returns_422(): void
    {
        $owner = User::factory()->create();
        $exchange = $this->pendingExchangeFor($owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/exchanges/{$exchange->id}", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }
}
