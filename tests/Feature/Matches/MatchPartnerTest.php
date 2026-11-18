<?php

namespace Tests\Feature\Matches;

use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class MatchPartnerTest extends TestCase
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

    public function test_user_one_sees_user_two_as_the_partner(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create(['name' => 'Beto']);
        MatchModel::factory()->create(['user_one_id' => $one->id, 'user_two_id' => $two->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($one))
            ->getJson('/api/matches');

        $response->assertStatus(200);
        $response->assertJsonPath('0.partner.id', $two->id);
        $response->assertJsonPath('0.partner.name', 'Beto');
    }

    public function test_user_two_sees_user_one_as_the_partner(): void
    {
        $one = User::factory()->create(['name' => 'Ana']);
        $two = User::factory()->create();
        MatchModel::factory()->create(['user_one_id' => $one->id, 'user_two_id' => $two->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($two))
            ->getJson('/api/matches');

        $response->assertStatus(200);
        $response->assertJsonPath('0.partner.id', $one->id);
        $response->assertJsonPath('0.partner.name', 'Ana');
    }

    public function test_the_partner_only_exposes_id_and_name(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        MatchModel::factory()->create(['user_one_id' => $one->id, 'user_two_id' => $two->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($one))
            ->getJson('/api/matches');

        $response->assertStatus(200);
        $response->assertJsonMissingPath('0.partner.email');
        $response->assertJsonMissingPath('0.partner.role');
        $response->assertJsonMissingPath('0.user_one');
        $response->assertJsonMissingPath('0.user_two');
    }

    public function test_the_existing_match_fields_are_kept(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        $match = MatchModel::factory()->create(['user_one_id' => $one->id, 'user_two_id' => $two->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($one))
            ->getJson('/api/matches');

        $response->assertStatus(200);
        $response->assertJsonPath('0.id', $match->id);
        $response->assertJsonPath('0.user_one_id', $one->id);
        $response->assertJsonPath('0.user_two_id', $two->id);
        $response->assertJsonPath('0.status', 'ACTIVE');
    }
}
