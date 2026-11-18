<?php

namespace Tests\Feature\Matches;

use App\Models\MatchModel;
use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class MatchPartnerUniverseTest extends TestCase
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

    public function test_the_partner_includes_the_id_and_style_of_their_universe(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $two->id, 'style' => 'DARK, UTILITY, KENDO']);
        MatchModel::factory()->create(['user_one_id' => $one->id, 'user_two_id' => $two->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($one))
            ->getJson('/api/matches');

        $response->assertStatus(200);
        $response->assertJsonPath('0.partner.universe.id', $universe->id);
        $response->assertJsonPath('0.partner.universe.style', 'DARK, UTILITY, KENDO');
    }

    public function test_the_partner_universe_is_null_when_they_have_none(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        MatchModel::factory()->create(['user_one_id' => $one->id, 'user_two_id' => $two->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($one))
            ->getJson('/api/matches');

        $response->assertStatus(200);
        $response->assertJsonPath('0.partner.universe', null);
    }

    public function test_the_partner_universe_only_exposes_id_and_style(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        Universe::factory()->create(['user_id' => $two->id]);
        MatchModel::factory()->create(['user_one_id' => $one->id, 'user_two_id' => $two->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($one))
            ->getJson('/api/matches');

        $response->assertStatus(200);
        $response->assertJsonMissingPath('0.partner.universe.name');
        $response->assertJsonMissingPath('0.partner.universe.user_id');
    }

    public function test_the_universe_of_the_requesting_user_is_not_included(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        Universe::factory()->create(['user_id' => $one->id]);
        MatchModel::factory()->create(['user_one_id' => $one->id, 'user_two_id' => $two->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($one))
            ->getJson('/api/matches');

        $response->assertStatus(200);
        $response->assertJsonPath('0.partner.universe', null);
    }
}
