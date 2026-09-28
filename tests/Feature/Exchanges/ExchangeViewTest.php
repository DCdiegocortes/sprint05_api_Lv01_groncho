<?php

namespace Tests\Feature\Exchanges;

use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ExchangeViewTest extends TestCase
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

    private function exchangeBetween(User $requester, User $owner): Exchange
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
        ]);
    }

    public function test_requester_sees_their_own_exchange_in_the_list(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $this->exchangeBetween($requester, $owner);
        Exchange::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($requester))
            ->getJson('/api/exchanges');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_item_owner_sees_the_exchange_in_the_list(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $this->exchangeBetween($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson('/api/exchanges');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_admin_sees_all_exchanges(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $this->exchangeBetween($requester, $owner);
        Exchange::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/exchanges');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    public function test_listing_exchanges_requires_authentication(): void
    {
        $response = $this->getJson('/api/exchanges');

        $response->assertStatus(401);
    }

    public function test_participant_can_view_an_exchange_detail(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->exchangeBetween($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(200);
    }

    public function test_admin_can_view_any_exchange(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->exchangeBetween($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(200);
    }

    public function test_non_participant_cannot_view_an_exchange(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $exchange = $this->exchangeBetween($requester, $owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(403);
    }

    public function test_viewing_exchange_requires_authentication(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $exchange = $this->exchangeBetween($requester, $owner);

        $response = $this->getJson("/api/exchanges/{$exchange->id}");

        $response->assertStatus(401);
    }
}
