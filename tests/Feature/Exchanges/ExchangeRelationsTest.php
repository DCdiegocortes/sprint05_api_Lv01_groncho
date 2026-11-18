<?php

namespace Tests\Feature\Exchanges;

use App\Enums\ExchangeStatus;
use App\Enums\ExchangeType;
use App\Models\Exchange;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ExchangeRelationsTest extends TestCase
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

    private function exchange(User $requester, User $owner, ExchangeType $type = ExchangeType::GIFT): Exchange
    {
        $match = MatchModel::factory()->create([
            'user_one_id' => $requester->id,
            'user_two_id' => $owner->id,
        ]);
        $requested = Item::factory()->create(['user_id' => $owner->id, 'name' => 'Silver boots', 'size' => 'M']);
        ItemImage::factory()->create(['item_id' => $requested->id]);
        $offered = null;

        if ($type === ExchangeType::TRADE) {
            $offered = Item::factory()->create(['user_id' => $requester->id, 'name' => 'Wrap skirt', 'size' => 'S']);
            ItemImage::factory()->count(2)->create(['item_id' => $offered->id]);
        }

        return Exchange::factory()->create([
            'match_id' => $match->id,
            'requester_id' => $requester->id,
            'requested_item_id' => $requested->id,
            'offered_item_id' => $offered?->id,
            'type' => $type,
        ]);
    }

    public function test_an_exchange_includes_the_requester_and_the_receiver(): void
    {
        $requester = User::factory()->create(['name' => 'Kaeru']);
        $owner = User::factory()->create(['name' => 'Norke']);
        $exchange = $this->exchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('requester.id', $requester->id);
        $response->assertJsonPath('requester.name', 'Kaeru');
        $response->assertJsonPath('receiver.id', $owner->id);
        $response->assertJsonPath('receiver.name', 'Norke');
    }

    public function test_an_exchange_includes_the_requested_item_with_its_images(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->exchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('requested_item.id', $exchange->requested_item_id);
        $response->assertJsonPath('requested_item.name', 'Silver boots');
        $response->assertJsonPath('requested_item.size', 'M');
        $response->assertJsonCount(1, 'requested_item.images');
    }

    public function test_a_gift_has_no_offered_item(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->exchange($requester, $owner, ExchangeType::GIFT);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('offered_item', null);
    }

    public function test_a_trade_includes_the_offered_item_with_its_images(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->exchange($requester, $owner, ExchangeType::TRADE);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('offered_item.id', $exchange->offered_item_id);
        $response->assertJsonPath('offered_item.name', 'Wrap skirt');
        $response->assertJsonCount(2, 'offered_item.images');
    }

    public function test_the_exchange_list_includes_the_same_relations(): void
    {
        $requester = User::factory()->create(['name' => 'Kaeru']);
        $owner = User::factory()->create(['name' => 'Norke']);
        $this->exchange($requester, $owner, ExchangeType::TRADE);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->getJson('/api/exchanges');

        $response->assertStatus(200);
        $response->assertJsonPath('0.requester.name', 'Kaeru');
        $response->assertJsonPath('0.receiver.name', 'Norke');
        $response->assertJsonCount(1, '0.requested_item.images');
        $response->assertJsonCount(2, '0.offered_item.images');
    }

    public function test_the_people_only_expose_id_and_name(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->exchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(200);
        $response->assertJsonMissingPath('requester.email');
        $response->assertJsonMissingPath('receiver.email');
        $response->assertJsonMissingPath('match');
    }

    public function test_the_receiver_stays_the_match_partner_after_the_items_change_owner(): void
    {
        $requester = User::factory()->create(['name' => 'Kaeru']);
        $owner = User::factory()->create(['name' => 'Norke']);
        $exchange = $this->exchange($requester, $owner);
        $exchange->requestedItem->update(['user_id' => $requester->id]);
        $exchange->update(['status' => ExchangeStatus::FINISHED]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('receiver.name', 'Norke');
    }

    public function test_the_existing_exchange_fields_are_kept(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->exchange($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('id', $exchange->id);
        $response->assertJsonPath('match_id', $exchange->match_id);
        $response->assertJsonPath('requester_id', $requester->id);
        $response->assertJsonPath('requested_item_id', $exchange->requested_item_id);
        $response->assertJsonPath('status', 'PENDING');
        $response->assertJsonPath('type', 'GIFT');
    }
}
