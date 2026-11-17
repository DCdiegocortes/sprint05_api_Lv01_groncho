<?php

namespace Tests\Feature\Items;

use App\Models\Exchange;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ItemDeleteWithExchangesTest extends TestCase
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

    public function test_an_item_requested_in_an_exchange_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);
        Exchange::factory()->create(['requested_item_id' => $item->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->deleteJson("/api/items/{$item->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }

    public function test_an_item_offered_in_an_exchange_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);
        Exchange::factory()->create(['offered_item_id' => $item->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->deleteJson("/api/items/{$item->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }
}
