<?php

namespace Tests\Feature\Moderation;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\Universe;
use App\Models\UniverseImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class BulkPhotoRemovalTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function storedPath(string $folder): string
    {
        return UploadedFile::fake()->image('photo.jpg')->store($folder, 'public');
    }

    private function universePhotos(Universe $universe, int $count): Collection
    {
        return collect(range(1, $count))->map(fn (int $position) => UniverseImage::factory()->create([
            'universe_id' => $universe->id,
            'path' => $this->storedPath('universe-images'),
            'position' => $position,
        ]));
    }

    private function itemPhotos(Item $item, int $count): Collection
    {
        return collect(range(1, $count))->map(fn () => ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => $this->storedPath('item-images'),
        ]));
    }

    private function notificationsOf(User $user): Collection
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->get()
            ->map(fn ($row) => ['type' => $row->type, ...json_decode($row->data, true)]);
    }

    public function test_the_admin_removes_several_universe_photos_at_once(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);
        [$first, $second, $kept] = $this->universePhotos($universe, 3)->all();

        $response = $this->asUser($this->admin())->deleteJson("/api/universes/{$universe->id}/images", [
            'image_ids' => [$first->id, $second->id],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('universe_images', ['id' => $first->id]);
        $this->assertDatabaseMissing('universe_images', ['id' => $second->id]);
        $this->assertDatabaseHas('universe_images', ['id' => $kept->id]);
        Storage::disk('public')->assertMissing($first->path);
        Storage::disk('public')->assertMissing($second->path);
        Storage::disk('public')->assertExists($kept->path);
        $this->assertEquals(
            [['type' => 'UNIVERSE_PHOTO_REMOVED', 'universe_id' => $universe->id, 'photos_removed' => 2]],
            $this->notificationsOf($owner)->all()
        );
    }

    public function test_the_owner_can_remove_several_of_their_universe_photos_without_a_notification(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);
        $photos = $this->universePhotos($universe, 2);

        $this->asUser($owner)->deleteJson("/api/universes/{$universe->id}/images", [
            'image_ids' => $photos->pluck('id')->all(),
        ])->assertStatus(200);

        $this->assertDatabaseCount('universe_images', 0);
        $this->assertCount(0, $this->notificationsOf($owner));
    }

    public function test_another_user_cannot_remove_universe_photos(): void
    {
        $universe = Universe::factory()->create();
        $photos = $this->universePhotos($universe, 2);

        $this->asUser(User::factory()->create())->deleteJson("/api/universes/{$universe->id}/images", [
            'image_ids' => $photos->pluck('id')->all(),
        ])->assertStatus(403);

        $this->assertDatabaseCount('universe_images', 2);
    }

    public function test_nothing_is_removed_when_a_photo_belongs_to_another_universe(): void
    {
        $universe = Universe::factory()->create();
        $photo = $this->universePhotos($universe, 1)->first();
        $foreign = $this->universePhotos(Universe::factory()->create(), 1)->first();

        $response = $this->asUser($this->admin())->deleteJson("/api/universes/{$universe->id}/images", [
            'image_ids' => [$photo->id, $foreign->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('image_ids');
        $this->assertDatabaseCount('universe_images', 2);
        Storage::disk('public')->assertExists($photo->path);
    }

    public function test_the_universe_photo_list_must_be_a_non_empty_list_without_repeats(): void
    {
        $universe = Universe::factory()->create();
        $photo = $this->universePhotos($universe, 1)->first();

        foreach ([[], 'all', [$photo->id, $photo->id]] as $imageIds) {
            $this->asUser($this->admin())->deleteJson("/api/universes/{$universe->id}/images", [
                'image_ids' => $imageIds,
            ])->assertStatus(422);
        }

        $this->assertDatabaseCount('universe_images', 1);
    }

    public function test_the_admin_removes_several_piece_photos_from_different_pieces(): void
    {
        $owner = User::factory()->create();
        $boots = Item::factory()->create(['user_id' => $owner->id, 'name' => 'Silver moto boots']);
        $bag = Item::factory()->create(['user_id' => $owner->id, 'name' => 'Melted chrome bag']);
        $bootsPhotos = $this->itemPhotos($boots, 2);
        [$bagPhoto, $keptBagPhoto] = $this->itemPhotos($bag, 2)->all();

        $response = $this->asUser($this->admin())->deleteJson('/api/items/images', [
            'image_ids' => [...$bootsPhotos->pluck('id')->all(), $bagPhoto->id],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('item_images', 1);
        $this->assertDatabaseHas('item_images', ['id' => $keptBagPhoto->id]);
        Storage::disk('public')->assertMissing($bagPhoto->path);
        Storage::disk('public')->assertExists($keptBagPhoto->path);
        $this->assertEqualsCanonicalizing([
            ['type' => 'ITEM_PHOTO_REMOVED', 'item_id' => $boots->id, 'item_name' => 'Silver moto boots', 'photos_removed' => 2],
            ['type' => 'ITEM_PHOTO_REMOVED', 'item_id' => $bag->id, 'item_name' => 'Melted chrome bag', 'photos_removed' => 1],
        ], $this->notificationsOf($owner)->all());
    }

    public function test_the_owner_can_remove_several_of_their_piece_photos_without_a_notification(): void
    {
        $owner = User::factory()->create();
        $photos = $this->itemPhotos(Item::factory()->create(['user_id' => $owner->id]), 2);

        $this->asUser($owner)->deleteJson('/api/items/images', [
            'image_ids' => $photos->pluck('id')->all(),
        ])->assertStatus(200);

        $this->assertDatabaseCount('item_images', 0);
        $this->assertCount(0, $this->notificationsOf($owner));
    }

    public function test_nothing_is_removed_when_one_piece_photo_belongs_to_someone_else(): void
    {
        $user = User::factory()->create();
        $own = $this->itemPhotos(Item::factory()->create(['user_id' => $user->id]), 1)->first();
        $foreign = $this->itemPhotos(Item::factory()->create(), 1)->first();

        $this->asUser($user)->deleteJson('/api/items/images', [
            'image_ids' => [$own->id, $foreign->id],
        ])->assertStatus(403);

        $this->assertDatabaseCount('item_images', 2);
        Storage::disk('public')->assertExists($own->path);
    }

    public function test_nothing_is_removed_when_a_piece_photo_does_not_exist(): void
    {
        $photo = $this->itemPhotos(Item::factory()->create(), 1)->first();

        $response = $this->asUser($this->admin())->deleteJson('/api/items/images', [
            'image_ids' => [$photo->id, 999],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('item_images', 1);
    }

    public function test_removing_one_photo_also_tells_how_many_were_removed(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);
        $photo = $this->universePhotos($universe, 1)->first();

        $this->asUser($this->admin())->deleteJson("/api/universes/{$universe->id}/images/{$photo->id}")->assertStatus(200);

        $this->assertSame(1, $this->notificationsOf($owner)->first()['photos_removed']);
    }

    public function test_removing_photos_requires_authentication(): void
    {
        $this->deleteJson('/api/items/images', ['image_ids' => [1]])->assertStatus(401);
    }
}
