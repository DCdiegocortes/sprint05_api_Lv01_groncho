<?php

namespace Tests\Feature\Universes;

use App\Models\Swipe;
use App\Models\Universe;
use App\Models\UniverseImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );
    }

    private function feedFor(User $user, string $query = '')
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->accessToken)
            ->getJson('/api/universes/feed'.$query);
    }

    public function test_the_feed_requires_authentication(): void
    {
        $this->getJson('/api/universes/feed')->assertStatus(401);
    }

    public function test_the_feed_does_not_show_my_own_universe(): void
    {
        $viewer = User::factory()->create();
        Universe::factory()->create(['user_id' => $viewer->id]);
        $other = Universe::factory()->create();

        $response = $this->feedFor($viewer);

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $other->id);
    }

    public function test_the_feed_hides_universes_i_already_liked(): void
    {
        $viewer = User::factory()->create();
        $liked = Universe::factory()->create();
        $pending = Universe::factory()->create();
        Swipe::factory()->create(['swiper_user_id' => $viewer->id, 'target_user_id' => $liked->user_id, 'liked' => true]);

        $response = $this->feedFor($viewer);

        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $pending->id);
    }

    public function test_the_feed_hides_universes_i_already_passed(): void
    {
        $viewer = User::factory()->create();
        $passed = Universe::factory()->create();
        $pending = Universe::factory()->create();
        Swipe::factory()->create(['swiper_user_id' => $viewer->id, 'target_user_id' => $passed->user_id, 'liked' => false]);

        $response = $this->feedFor($viewer);

        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $pending->id);
    }

    public function test_the_feed_still_shows_users_who_swiped_on_me(): void
    {
        $viewer = User::factory()->create();
        $admirer = Universe::factory()->create();
        Swipe::factory()->create(['swiper_user_id' => $admirer->user_id, 'target_user_id' => $viewer->id, 'liked' => true]);

        $response = $this->feedFor($viewer);

        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $admirer->id);
    }

    public function test_the_feed_returns_at_most_ten_universes(): void
    {
        $viewer = User::factory()->create();
        Universe::factory()->count(12)->create();

        $this->feedFor($viewer)->assertJsonCount(10);
    }

    public function test_the_feed_is_empty_when_there_is_nothing_left_to_swipe(): void
    {
        $viewer = User::factory()->create();
        $universe = Universe::factory()->create();
        Swipe::factory()->create(['swiper_user_id' => $viewer->id, 'target_user_id' => $universe->user_id]);

        $response = $this->feedFor($viewer);

        $response->assertStatus(200);
        $response->assertExactJson([]);
    }

    public function test_the_feed_shows_the_owner_and_the_images_of_each_universe(): void
    {
        Storage::fake('public');
        $viewer = User::factory()->create();
        $owner = User::factory()->create(['name' => 'Ana']);
        $universe = Universe::factory()->create(['user_id' => $owner->id, 'description' => 'Soft tones']);
        $image = UniverseImage::factory()->create(['universe_id' => $universe->id]);

        $response = $this->feedFor($viewer);

        $response->assertJsonPath('0.description', 'Soft tones');
        $response->assertJsonPath('0.user.id', $owner->id);
        $response->assertJsonPath('0.user.name', 'Ana');
        $response->assertJsonPath('0.images.0.url', Storage::disk('public')->url($image->path));
    }

    public function test_the_feed_can_be_searched_by_style(): void
    {
        $viewer = User::factory()->create();
        $minimal = Universe::factory()->create(['name' => 'Calm', 'style' => 'MINIMAL, NEUTRAL']);
        Universe::factory()->create(['name' => 'Loud', 'style' => 'STREETWEAR']);

        $response = $this->feedFor($viewer, '?search=mini');

        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $minimal->id);
    }

    public function test_the_feed_can_be_searched_by_universe_name(): void
    {
        $viewer = User::factory()->create();
        $soft = Universe::factory()->create(['name' => 'Soft Minimal', 'style' => 'NEUTRAL']);
        Universe::factory()->create(['name' => 'Street Vintage', 'style' => 'VINTAGE']);

        $response = $this->feedFor($viewer, '?search=soft');

        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $soft->id);
    }

    public function test_the_feed_can_be_searched_by_owner_name(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $ana = Universe::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Ana Lopez']),
            'name' => 'Garden',
            'style' => 'BOHO',
        ]);
        Universe::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Beto Ruiz']),
            'name' => 'Street',
            'style' => 'URBAN',
        ]);

        $response = $this->feedFor($viewer, '?search=ana');

        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $ana->id);
    }

    public function test_searching_the_feed_still_hides_my_own_and_swiped_universes(): void
    {
        $viewer = User::factory()->create();
        Universe::factory()->create(['user_id' => $viewer->id, 'style' => 'MINIMAL']);
        $swiped = Universe::factory()->create(['style' => 'MINIMAL']);
        Swipe::factory()->create(['swiper_user_id' => $viewer->id, 'target_user_id' => $swiped->user_id]);
        $pending = Universe::factory()->create(['style' => 'MINIMAL']);

        $response = $this->feedFor($viewer, '?search=minimal');

        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $pending->id);
    }

    public function test_the_full_universe_list_is_unchanged(): void
    {
        $viewer = User::factory()->create();
        Universe::factory()->create(['user_id' => $viewer->id]);
        Universe::factory()->count(11)->create();

        $this->withHeader('Authorization', 'Bearer '.$viewer->createToken('test-token')->accessToken)
            ->getJson('/api/universes')
            ->assertJsonCount(12);
    }
}
