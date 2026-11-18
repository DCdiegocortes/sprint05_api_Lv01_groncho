<?php

namespace Tests\Feature\Profile;

use App\Models\MatchModel;
use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ProfileHandleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );
    }

    private function asUser(User $user)
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->accessToken);
    }

    private function handleFor(string $name): mixed
    {
        return $this->asUser(User::factory()->create(['name' => $name]))->getJson('/api/profile')->json('handle');
    }

    public function test_the_handle_is_the_name_in_lowercase_without_spaces(): void
    {
        $this->assertSame('diegocortes', $this->handleFor('Diego Cortes'));
    }

    public function test_the_handle_drops_accents(): void
    {
        $this->assertSame('anamariaperez', $this->handleFor('Ana María Pérez'));
    }

    public function test_the_handle_drops_symbols_and_keeps_numbers(): void
    {
        $this->assertSame('oneilsmith99', $this->handleFor("O'Neil-Smith 99."));
    }

    public function test_a_name_without_letters_or_numbers_has_no_handle(): void
    {
        $response = $this->asUser(User::factory()->create(['name' => '✨ ✨']))->getJson('/api/profile');

        $response->assertJsonStructure(['handle']);
        $response->assertJsonPath('handle', null);
    }

    public function test_the_handle_follows_a_name_change(): void
    {
        $user = User::factory()->create(['name' => 'Diego Cortes']);

        $response = $this->asUser($user)->putJson('/api/profile', ['name' => 'Kaeru']);

        $response->assertJsonPath('handle', 'kaeru');
    }

    public function test_the_handle_is_not_stored_in_the_database(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'handle'));
    }

    public function test_the_match_partner_shows_the_handle(): void
    {
        $viewer = User::factory()->create();
        $partner = User::factory()->create(['name' => 'Beto Ruiz']);
        MatchModel::factory()->create(['user_one_id' => $viewer->id, 'user_two_id' => $partner->id]);

        $this->asUser($viewer)->getJson('/api/matches')->assertJsonPath('0.partner.handle', 'betoruiz');
    }

    public function test_the_feed_owner_shows_the_handle(): void
    {
        Universe::factory()->create(['user_id' => User::factory()->create(['name' => 'Ana Lopez'])->id]);

        $this->asUser(User::factory()->create())->getJson('/api/universes/feed')->assertJsonPath('0.user.handle', 'analopez');
    }
}
