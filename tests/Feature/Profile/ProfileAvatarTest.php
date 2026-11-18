<?php

namespace Tests\Feature\Profile;

use App\Models\Exchange;
use App\Models\MatchModel;
use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );

        Storage::fake('public');
    }

    private function asUser(User $user)
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->accessToken);
    }

    private function userWithAvatar(array $attributes = []): User
    {
        $path = UploadedFile::fake()->image('old.jpg')->store('avatars', 'public');

        return User::factory()->create(['avatar' => $path, ...$attributes]);
    }

    public function test_a_user_can_upload_an_avatar_from_the_device(): void
    {
        $user = User::factory()->create();

        $response = $this->asUser($user)->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);

        $response->assertStatus(200);
        $path = $user->fresh()->avatar;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $response->assertJsonPath('avatar_url', Storage::disk('public')->url($path));
        $response->assertJsonMissingPath('avatar');
    }

    public function test_replacing_the_avatar_deletes_the_previous_file(): void
    {
        $user = $this->userWithAvatar();
        $oldPath = $user->avatar;

        $this->asUser($user)->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('new.jpg'),
        ])->assertStatus(200);

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->fresh()->avatar);
    }

    public function test_a_user_can_remove_the_avatar(): void
    {
        $user = $this->userWithAvatar();
        $oldPath = $user->avatar;

        $response = $this->asUser($user)->deleteJson('/api/profile/avatar');

        $response->assertStatus(200);
        $response->assertJsonPath('avatar_url', null);
        $this->assertNull($user->fresh()->avatar);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_removing_the_avatar_when_there_is_none_still_works(): void
    {
        $response = $this->asUser(User::factory()->create())->deleteJson('/api/profile/avatar');

        $response->assertStatus(200);
        $response->assertJsonPath('avatar_url', null);
    }

    public function test_a_user_without_avatar_has_a_null_avatar_url(): void
    {
        $response = $this->asUser(User::factory()->create())->getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonStructure(['avatar_url']);
        $response->assertJsonPath('avatar_url', null);
    }

    public function test_the_avatar_must_be_an_image(): void
    {
        $response = $this->asUser(User::factory()->create())->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('avatar');
    }

    public function test_the_avatar_is_required(): void
    {
        $response = $this->asUser(User::factory()->create())->postJson('/api/profile/avatar', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('avatar');
    }

    public function test_the_avatar_cannot_be_bigger_than_ten_megabytes(): void
    {
        $response = $this->asUser(User::factory()->create())->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('huge.jpg')->size(10241),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('avatar');
    }

    public function test_uploading_an_avatar_requires_authentication(): void
    {
        $this->postJson('/api/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg')])
            ->assertStatus(401);
    }

    public function test_removing_an_avatar_requires_authentication(): void
    {
        $this->deleteJson('/api/profile/avatar')->assertStatus(401);
    }

    public function test_the_match_partner_shows_the_avatar(): void
    {
        $viewer = User::factory()->create();
        $partner = $this->userWithAvatar();
        MatchModel::factory()->create(['user_one_id' => $viewer->id, 'user_two_id' => $partner->id]);

        $response = $this->asUser($viewer)->getJson('/api/matches');

        $response->assertJsonPath('0.partner.avatar_url', Storage::disk('public')->url($partner->avatar));
    }

    public function test_the_feed_owner_shows_the_avatar(): void
    {
        $owner = $this->userWithAvatar();
        Universe::factory()->create(['user_id' => $owner->id]);

        $response = $this->asUser(User::factory()->create())->getJson('/api/universes/feed');

        $response->assertJsonPath('0.user.avatar_url', Storage::disk('public')->url($owner->avatar));
    }

    public function test_the_exchange_requester_and_receiver_show_their_avatars(): void
    {
        $requester = $this->userWithAvatar();
        $receiver = $this->userWithAvatar();
        $match = MatchModel::factory()->create(['user_one_id' => $requester->id, 'user_two_id' => $receiver->id]);
        $exchange = Exchange::factory()->create(['match_id' => $match->id, 'requester_id' => $requester->id]);

        $response = $this->asUser($requester)->getJson("/api/exchanges/{$exchange->id}");

        $response->assertJsonPath('requester.avatar_url', Storage::disk('public')->url($requester->avatar));
        $response->assertJsonPath('receiver.avatar_url', Storage::disk('public')->url($receiver->avatar));
    }

    public function test_the_admin_user_list_shows_the_avatar(): void
    {
        $user = $this->userWithAvatar();

        $response = $this->asUser(User::factory()->create(['role' => 'admin']))->getJson('/api/users');

        $response->assertJsonFragment(['avatar_url' => Storage::disk('public')->url($user->avatar)]);
    }
}
