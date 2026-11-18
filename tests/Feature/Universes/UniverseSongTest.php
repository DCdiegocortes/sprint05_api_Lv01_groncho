<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseSongTest extends TestCase
{
    use RefreshDatabase;

    private const TRACK_ID = '4uLU6hMCjMI75M1A2tKUQC';

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );

        Http::preventStrayRequests();
    }

    private function fakeSpotify(): void
    {
        Http::fake([
            'open.spotify.com/oembed*' => Http::response([
                'title' => 'Never Gonna Give You Up',
                'thumbnail_url' => 'https://image-cdn-ak.spotifycdn.com/image/cover.jpg',
            ]),
        ]);
    }

    private function updateUniverse(User $user, Universe $universe, array $data)
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->accessToken)
            ->putJson("/api/universes/{$universe->id}", $data);
    }

    private function ownUniverse(): array
    {
        $user = User::factory()->create();

        return [$user, Universe::factory()->create(['user_id' => $user->id])];
    }

    public function test_the_owner_can_add_a_song_from_a_spotify_link(): void
    {
        $this->fakeSpotify();
        [$user, $universe] = $this->ownUniverse();

        $response = $this->updateUniverse($user, $universe, [
            'spotify_url' => 'https://open.spotify.com/track/'.self::TRACK_ID,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('spotify_track_id', self::TRACK_ID);
        $response->assertJsonPath('spotify_track_title', 'Never Gonna Give You Up');
        $response->assertJsonPath('spotify_track_cover_url', 'https://image-cdn-ak.spotifycdn.com/image/cover.jpg');
        $this->assertDatabaseHas('universes', [
            'id' => $universe->id,
            'spotify_track_id' => self::TRACK_ID,
            'spotify_track_title' => 'Never Gonna Give You Up',
        ]);
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://open.spotify.com/oembed')
            && $request['url'] === 'https://open.spotify.com/track/'.self::TRACK_ID);
    }

    public function test_a_shared_link_with_tracking_and_language_parts_is_accepted(): void
    {
        $this->fakeSpotify();
        [$user, $universe] = $this->ownUniverse();

        $response = $this->updateUniverse($user, $universe, [
            'spotify_url' => 'https://open.spotify.com/intl-es/track/'.self::TRACK_ID.'?si=a1b2c3d4e5f6',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('spotify_track_id', self::TRACK_ID);
    }

    public function test_the_song_is_saved_without_title_and_cover_when_spotify_fails(): void
    {
        Http::fake(['open.spotify.com/oembed*' => Http::response(null, 500)]);
        [$user, $universe] = $this->ownUniverse();

        $response = $this->updateUniverse($user, $universe, [
            'spotify_url' => 'https://open.spotify.com/track/'.self::TRACK_ID,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('spotify_track_id', self::TRACK_ID);
        $response->assertJsonPath('spotify_track_title', null);
        $response->assertJsonPath('spotify_track_cover_url', null);
    }

    public function test_the_song_is_saved_without_title_and_cover_when_spotify_cannot_be_reached(): void
    {
        Http::fake(['open.spotify.com/oembed*' => Http::failedConnection()]);
        [$user, $universe] = $this->ownUniverse();

        $response = $this->updateUniverse($user, $universe, [
            'spotify_url' => 'https://open.spotify.com/track/'.self::TRACK_ID,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('spotify_track_id', self::TRACK_ID);
        $response->assertJsonPath('spotify_track_title', null);
    }

    public function test_the_owner_can_remove_the_song(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $universe = Universe::factory()->create([
            'user_id' => $user->id,
            'spotify_track_id' => self::TRACK_ID,
            'spotify_track_title' => 'Never Gonna Give You Up',
            'spotify_track_cover_url' => 'https://image-cdn-ak.spotifycdn.com/image/cover.jpg',
        ]);

        $response = $this->updateUniverse($user, $universe, ['spotify_url' => null]);

        $response->assertStatus(200);
        $response->assertJsonPath('spotify_track_id', null);
        $response->assertJsonPath('spotify_track_title', null);
        $response->assertJsonPath('spotify_track_cover_url', null);
        Http::assertNothingSent();
    }

    public function test_links_that_are_not_a_spotify_track_are_rejected(): void
    {
        Http::fake();
        [$user, $universe] = $this->ownUniverse();

        foreach ([
            'https://open.spotify.com/album/'.self::TRACK_ID,
            'https://open.spotify.com/playlist/'.self::TRACK_ID,
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://open.spotify.com.evil.com/track/'.self::TRACK_ID,
            'just some text',
        ] as $link) {
            $response = $this->updateUniverse($user, $universe, ['spotify_url' => $link]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('spotify_url');
        }

        Http::assertNothingSent();
    }

    public function test_updating_other_fields_keeps_the_song(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $universe = Universe::factory()->create([
            'user_id' => $user->id,
            'spotify_track_id' => self::TRACK_ID,
            'spotify_track_title' => 'Never Gonna Give You Up',
        ]);

        $response = $this->updateUniverse($user, $universe, ['name' => 'Renamed']);

        $response->assertStatus(200);
        $response->assertJsonPath('spotify_track_id', self::TRACK_ID);
        $response->assertJsonPath('spotify_track_title', 'Never Gonna Give You Up');
        Http::assertNothingSent();
    }

    public function test_another_user_cannot_change_the_song(): void
    {
        Http::fake();
        $universe = Universe::factory()->create();

        $response = $this->updateUniverse(User::factory()->create(), $universe, [
            'spotify_url' => 'https://open.spotify.com/track/'.self::TRACK_ID,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('universes', ['id' => $universe->id, 'spotify_track_id' => null]);
        Http::assertNothingSent();
    }
}
