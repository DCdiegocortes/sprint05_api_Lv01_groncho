<?php

namespace Tests\Feature\Exchanges;

use App\Enums\OfferType;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ExchangeOfferTypeTest extends TestCase
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

    private function request(OfferType $offerType, string $exchangeType)
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $match = MatchModel::factory()->create([
            'user_one_id' => $requester->id,
            'user_two_id' => $owner->id,
        ]);
        $requested = Item::factory()->create(['user_id' => $owner->id, 'offer_type' => $offerType]);
        $offered = $exchangeType === 'TRADE'
            ? Item::factory()->create(['user_id' => $requester->id])
            : null;

        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->postJson('/api/exchanges', [
                'match_id' => $match->id,
                'requested_item_id' => $requested->id,
                'offered_item_id' => $offered?->id,
                'type' => $exchangeType,
            ]);
    }

    public function test_an_item_offered_as_trade_accepts_a_trade(): void
    {
        $this->request(OfferType::TRADE, 'TRADE')->assertStatus(201);
    }

    public function test_an_item_offered_as_trade_rejects_a_gift(): void
    {
        $response = $this->request(OfferType::TRADE, 'GIFT');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type');
    }

    public function test_an_item_offered_as_gift_accepts_a_gift(): void
    {
        $this->request(OfferType::GIFT, 'GIFT')->assertStatus(201);
    }

    public function test_an_item_offered_as_gift_rejects_a_trade(): void
    {
        $response = $this->request(OfferType::GIFT, 'TRADE');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type');
    }

    public function test_an_item_offered_as_both_accepts_a_trade(): void
    {
        $this->request(OfferType::BOTH, 'TRADE')->assertStatus(201);
    }

    public function test_an_item_offered_as_both_accepts_a_gift(): void
    {
        $this->request(OfferType::BOTH, 'GIFT')->assertStatus(201);
    }
}
