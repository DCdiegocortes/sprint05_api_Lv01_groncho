<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\UniverseImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseImageOrderTest extends TestCase
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

    private function universeWithImages(int $count): array
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);
        $images = collect(range(1, $count))->map(fn (int $position) => UniverseImage::factory()->create([
            'universe_id' => $universe->id,
            'position' => $position,
        ]));

        return [$owner, $universe, $images];
    }

    private function reorder(User $user, Universe $universe, array $imageIds)
    {
        return $this->asUser($user)->putJson("/api/universes/{$universe->id}/images/order", [
            'image_ids' => $imageIds,
        ]);
    }

    public function test_the_owner_can_reorder_the_photos(): void
    {
        [$owner, $universe, $images] = $this->universeWithImages(3);
        [$first, $second, $third] = $images->pluck('id')->all();

        $response = $this->reorder($owner, $universe, [$third, $first, $second]);

        $response->assertStatus(200);
        $response->assertJsonPath('0.id', $third);
        $response->assertJsonPath('1.id', $first);
        $response->assertJsonPath('2.id', $second);
        $response->assertJsonPath('0.url', Storage::disk('public')->url($images[2]->path));
        $this->assertDatabaseHas('universe_images', ['id' => $third, 'position' => 1]);
        $this->assertDatabaseHas('universe_images', ['id' => $first, 'position' => 2]);
        $this->assertDatabaseHas('universe_images', ['id' => $second, 'position' => 3]);
    }

    public function test_my_universe_shows_the_photos_in_their_order(): void
    {
        [$owner, $universe, $images] = $this->universeWithImages(3);
        $images[0]->update(['position' => 3]);
        $images[2]->update(['position' => 1]);

        $response = $this->asUser($owner)->getJson('/api/universes/mine');

        $response->assertJsonPath('images.0.id', $images[2]->id);
        $response->assertJsonPath('images.1.id', $images[1]->id);
        $response->assertJsonPath('images.2.id', $images[0]->id);
    }

    public function test_the_feed_shows_the_photos_in_their_order(): void
    {
        [, , $images] = $this->universeWithImages(2);
        $images[0]->update(['position' => 2]);
        $images[1]->update(['position' => 1]);

        $response = $this->asUser(User::factory()->create())->getJson('/api/universes/feed');

        $response->assertJsonPath('0.images.0.id', $images[1]->id);
        $response->assertJsonPath('0.images.1.id', $images[0]->id);
    }

    public function test_new_photos_are_added_at_the_end(): void
    {
        [$owner, $universe] = $this->universeWithImages(2);

        $response = $this->asUser($owner)->postJson("/api/universes/{$universe->id}/images", [
            'images' => [
                UploadedFile::fake()->image('third.jpg'),
                UploadedFile::fake()->image('fourth.jpg'),
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('0.position', 3);
        $response->assertJsonPath('1.position', 4);
    }

    public function test_the_first_photo_of_a_universe_gets_the_first_position(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);

        $response = $this->asUser($owner)->postJson("/api/universes/{$universe->id}/images", [
            'images' => [UploadedFile::fake()->image('first.jpg')],
        ]);

        $response->assertJsonPath('0.position', 1);
    }

    public function test_deleting_a_photo_keeps_the_order_of_the_others(): void
    {
        [$owner, $universe, $images] = $this->universeWithImages(3);

        $this->asUser($owner)->deleteJson("/api/universes/{$universe->id}/images/{$images[1]->id}")->assertStatus(200);

        $this->assertDatabaseHas('universe_images', ['id' => $images[0]->id, 'position' => 1]);
        $this->assertDatabaseHas('universe_images', ['id' => $images[2]->id, 'position' => 3]);
    }

    public function test_the_order_must_include_every_photo(): void
    {
        [$owner, $universe, $images] = $this->universeWithImages(3);

        $response = $this->reorder($owner, $universe, [$images[1]->id, $images[0]->id]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('image_ids');
    }

    public function test_the_order_cannot_repeat_a_photo(): void
    {
        [$owner, $universe, $images] = $this->universeWithImages(2);

        $response = $this->reorder($owner, $universe, [$images[0]->id, $images[0]->id, $images[1]->id]);

        $response->assertStatus(422);
    }

    public function test_the_order_cannot_include_a_photo_from_another_universe(): void
    {
        [$owner, $universe, $images] = $this->universeWithImages(2);
        $foreign = UniverseImage::factory()->create();

        $response = $this->reorder($owner, $universe, [$images[0]->id, $images[1]->id, $foreign->id]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('universe_images', ['id' => $images[0]->id, 'position' => 1]);
    }

    public function test_the_order_must_be_a_list_of_ids(): void
    {
        [$owner, $universe] = $this->universeWithImages(1);

        $response = $this->asUser($owner)->putJson("/api/universes/{$universe->id}/images/order", [
            'image_ids' => 'first',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('image_ids');
    }

    public function test_another_user_cannot_reorder_the_photos(): void
    {
        [, $universe, $images] = $this->universeWithImages(2);

        $response = $this->reorder(User::factory()->create(), $universe, [$images[1]->id, $images[0]->id]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('universe_images', ['id' => $images[0]->id, 'position' => 1]);
    }

    public function test_an_admin_cannot_reorder_the_photos_of_a_universe(): void
    {
        [, $universe, $images] = $this->universeWithImages(2);

        $response = $this->reorder(User::factory()->create(['role' => 'admin']), $universe, [$images[1]->id, $images[0]->id]);

        $response->assertStatus(403);
    }

    public function test_reordering_requires_authentication(): void
    {
        [, $universe, $images] = $this->universeWithImages(1);

        $this->putJson("/api/universes/{$universe->id}/images/order", ['image_ids' => [$images[0]->id]])
            ->assertStatus(401);
    }
}
