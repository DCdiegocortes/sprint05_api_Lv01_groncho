<?php

namespace Tests\Feature\Rankings;

use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UserRankingTest extends TestCase
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

    private function requestExchange(User $requester, User $owner, string $status): Exchange
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

    public function test_users_ranking_orders_by_success_rate_descending(): void
    {
        $viewer = User::factory()->create();

        $bestUser = User::factory()->create();
        $owner1 = User::factory()->create();
        $this->requestExchange($bestUser, $owner1, 'FINISHED');

        $worstUser = User::factory()->create();
        $owner2 = User::factory()->create();
        $this->requestExchange($worstUser, $owner2, 'PENDING');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($viewer))
            ->getJson('/api/users/ranking');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals($bestUser->id, $data[0]['user']['id']);
        $this->assertEquals($worstUser->id, $data[1]['user']['id']);
    }

    public function test_users_with_no_requests_are_excluded_from_ranking(): void
    {
        $viewer = User::factory()->create();
        User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($viewer))
            ->getJson('/api/users/ranking');

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    }

    public function test_users_ranking_requires_authentication(): void
    {
        $response = $this->getJson('/api/users/ranking');

        $response->assertStatus(401);
    }

    public function test_ranking_best_returns_the_top_user(): void
    {
        $viewer = User::factory()->create();

        $bestUser = User::factory()->create();
        $owner1 = User::factory()->create();
        $this->requestExchange($bestUser, $owner1, 'FINISHED');

        $worstUser = User::factory()->create();
        $owner2 = User::factory()->create();
        $this->requestExchange($worstUser, $owner2, 'PENDING');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($viewer))
            ->getJson('/api/users/ranking/best');

        $response->assertStatus(200);
        $response->assertJsonPath('user.id', $bestUser->id);
    }

    public function test_ranking_worst_returns_the_bottom_user(): void
    {
        $viewer = User::factory()->create();

        $bestUser = User::factory()->create();
        $owner1 = User::factory()->create();
        $this->requestExchange($bestUser, $owner1, 'FINISHED');

        $worstUser = User::factory()->create();
        $owner2 = User::factory()->create();
        $this->requestExchange($worstUser, $owner2, 'PENDING');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($viewer))
            ->getJson('/api/users/ranking/worst');

        $response->assertStatus(200);
        $response->assertJsonPath('user.id', $worstUser->id);
    }
}
