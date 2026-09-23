<?php

namespace Tests\Feature\Matches;

use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class MatchTest extends TestCase
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

    public function test_user_can_list_their_own_matches(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        MatchModel::factory()->create(['user_one_id' => $user->id, 'user_two_id' => $other->id]);
        MatchModel::factory()->count(2)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/matches');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_listing_matches_requires_authentication(): void
    {
        $response = $this->getJson('/api/matches');

        $response->assertStatus(401);
    }

    public function test_participant_can_close_their_match(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $match = MatchModel::factory()->create(['user_one_id' => $user->id, 'user_two_id' => $other->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->deleteJson("/api/matches/{$match->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('matches', ['id' => $match->id, 'status' => 'CLOSED']);
    }

    public function test_admin_can_close_any_match(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $match = MatchModel::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->deleteJson("/api/matches/{$match->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('matches', ['id' => $match->id, 'status' => 'CLOSED']);
    }

    public function test_non_participant_cannot_close_a_match(): void
    {
        $intruder = User::factory()->create();
        $match = MatchModel::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->deleteJson("/api/matches/{$match->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('matches', ['id' => $match->id, 'status' => 'ACTIVE']);
    }

    public function test_closing_match_requires_authentication(): void
    {
        $match = MatchModel::factory()->create();

        $response = $this->deleteJson("/api/matches/{$match->id}");

        $response->assertStatus(401);
    }
}
