<?php

namespace Tests\Feature\Rankings;

use App\Models\Exchange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UserRankingPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );

        $this->requester = User::factory()->create(['name' => 'Kaeru', 'email' => 'kaeru@example.com']);
        Exchange::factory()->create(['requester_id' => $this->requester->id]);
    }

    private function ranking(string $path)
    {
        return $this->withHeader('Authorization', 'Bearer '.User::factory()->create()->createToken('test-token')->accessToken)
            ->getJson($path);
    }

    private function assertOnlyPublicFields($response, string $userPath): void
    {
        $response->assertStatus(200);
        $response->assertJsonPath("{$userPath}.id", $this->requester->id);
        $response->assertJsonPath("{$userPath}.name", 'Kaeru');
        $response->assertJsonPath("{$userPath}.handle", 'kaeru');
        $this->assertArrayHasKey('avatar_url', $response->json($userPath));
        $response->assertJsonMissingPath("{$userPath}.email");
        $response->assertJsonMissingPath("{$userPath}.role");
        $response->assertJsonMissingPath("{$userPath}.email_verified_at");
        $response->assertJsonMissingPath("{$userPath}.created_at");
    }

    public function test_the_user_ranking_does_not_show_emails(): void
    {
        $response = $this->ranking('/api/users/ranking');

        $this->assertOnlyPublicFields($response, '0.user');
        $response->assertDontSee('kaeru@example.com');
    }

    public function test_the_best_user_does_not_show_the_email(): void
    {
        $response = $this->ranking('/api/users/ranking/best');

        $this->assertOnlyPublicFields($response, 'user');
        $response->assertDontSee('kaeru@example.com');
    }

    public function test_the_worst_user_does_not_show_the_email(): void
    {
        $response = $this->ranking('/api/users/ranking/worst');

        $this->assertOnlyPublicFields($response, 'user');
        $response->assertDontSee('kaeru@example.com');
    }
}
