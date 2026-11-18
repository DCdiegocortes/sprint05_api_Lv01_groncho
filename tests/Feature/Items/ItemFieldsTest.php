<?php

namespace Tests\Feature\Items;

use App\Enums\ItemCondition;
use App\Enums\ItemType;
use App\Enums\OfferType;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ItemFieldsTest extends TestCase
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

    private function createItem(array $data)
    {
        $user = User::factory()->create();

        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/items', ['name' => 'Silver boots', ...$data]);
    }

    public function test_an_item_can_be_created_with_condition_type_and_offer(): void
    {
        $response = $this->createItem([
            'item_condition' => 'EXCELLENT',
            'type' => 'ACCESSORIES',
            'offer_type' => 'GIFT',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('item_condition', 'EXCELLENT');
        $response->assertJsonPath('type', 'ACCESSORIES');
        $response->assertJsonPath('offer_type', 'GIFT');
        $this->assertDatabaseHas('items', [
            'name' => 'Silver boots',
            'item_condition' => 'EXCELLENT',
            'type' => 'ACCESSORIES',
            'offer_type' => 'GIFT',
        ]);
    }

    public function test_an_item_created_without_the_new_fields_gets_the_defaults(): void
    {
        $response = $this->createItem([]);

        $response->assertStatus(201);
        $response->assertJsonPath('item_condition', 'GOOD');
        $response->assertJsonPath('type', 'CLOTHES');
        $response->assertJsonPath('offer_type', 'TRADE');
    }

    public function test_an_item_can_offer_both_trade_and_gift(): void
    {
        $response = $this->createItem(['offer_type' => 'BOTH']);

        $response->assertStatus(201);
        $response->assertJsonPath('offer_type', 'BOTH');
    }

    public function test_an_invalid_condition_is_rejected(): void
    {
        $response = $this->createItem(['item_condition' => 'BROKEN']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('item_condition');
    }

    public function test_an_invalid_type_is_rejected(): void
    {
        $response = $this->createItem(['type' => 'SHOES']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type');
    }

    public function test_an_invalid_offer_is_rejected(): void
    {
        $response = $this->createItem(['offer_type' => 'SELL']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('offer_type');
    }

    public function test_the_owner_can_update_the_new_fields(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/items/{$item->id}", [
                'item_condition' => 'FAIR',
                'type' => 'ACCESSORIES',
                'offer_type' => 'GIFT',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('item_condition', 'FAIR');
        $response->assertJsonPath('type', 'ACCESSORIES');
        $response->assertJsonPath('offer_type', 'GIFT');
    }

    public function test_updating_only_the_name_keeps_the_new_fields(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $owner->id,
            'item_condition' => ItemCondition::NEW,
            'type' => ItemType::ACCESSORIES,
            'offer_type' => OfferType::GIFT,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/items/{$item->id}", ['name' => 'Renamed']);

        $response->assertStatus(200);
        $response->assertJsonPath('item_condition', 'NEW');
        $response->assertJsonPath('type', 'ACCESSORIES');
        $response->assertJsonPath('offer_type', 'GIFT');
    }

    public function test_an_invalid_value_is_rejected_when_updating(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/items/{$item->id}", ['offer_type' => 'SELL']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('offer_type');
    }

    public function test_viewing_an_item_includes_the_new_fields(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $owner->id,
            'item_condition' => ItemCondition::EXCELLENT,
            'type' => ItemType::CLOTHES,
            'offer_type' => OfferType::BOTH,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson("/api/items/{$item->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('item_condition', 'EXCELLENT');
        $response->assertJsonPath('type', 'CLOTHES');
        $response->assertJsonPath('offer_type', 'BOTH');
    }
}
