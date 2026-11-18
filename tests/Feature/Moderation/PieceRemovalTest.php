<?php

namespace Tests\Feature\Moderation;

use App\Models\Exchange;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class PieceRemovalTest extends TestCase
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

    private function pieceWithPhotos(array $attributes = [], int $photos = 2): Item
    {
        $item = Item::factory()->create($attributes);

        foreach (range(1, $photos) as $ignored) {
            ItemImage::factory()->create([
                'item_id' => $item->id,
                'path' => UploadedFile::fake()->image('photo.jpg')->store('item-images', 'public'),
            ]);
        }

        return $item->load('images');
    }

    private function notificationsOf(User $user): Collection
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->get()
            ->map(fn ($row) => ['type' => $row->type, ...json_decode($row->data, true)]);
    }

    private function assertPhotoFilesMissing(Item $item): void
    {
        foreach ($item->images as $image) {
            Storage::disk('public')->assertMissing($image->path);
        }
    }

    private function assertPhotoFilesExist(Item $item): void
    {
        foreach ($item->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_deleting_one_piece_also_deletes_its_photo_files(): void
    {
        $owner = User::factory()->create();
        $piece = $this->pieceWithPhotos(['user_id' => $owner->id]);

        $this->asUser($owner)->deleteJson("/api/items/{$piece->id}")->assertStatus(200);

        $this->assertDatabaseCount('item_images', 0);
        $this->assertPhotoFilesMissing($piece);
    }

    public function test_the_admin_removes_several_pieces_with_their_photos_at_once(): void
    {
        $owner = User::factory()->create();
        $boots = $this->pieceWithPhotos(['user_id' => $owner->id, 'name' => 'Silver moto boots']);
        $bag = $this->pieceWithPhotos(['user_id' => $owner->id, 'name' => 'Melted chrome bag'], 1);
        $kept = $this->pieceWithPhotos(['user_id' => $owner->id]);

        $response = $this->asUser($this->admin())->deleteJson('/api/items', ['item_ids' => [$boots->id, $bag->id]]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('items', ['id' => $boots->id]);
        $this->assertDatabaseMissing('items', ['id' => $bag->id]);
        $this->assertDatabaseHas('items', ['id' => $kept->id]);
        $this->assertPhotoFilesMissing($boots);
        $this->assertPhotoFilesMissing($bag);
        $this->assertPhotoFilesExist($kept);
        $this->assertEqualsCanonicalizing([
            ['type' => 'ITEM_REMOVED', 'item_name' => 'Silver moto boots'],
            ['type' => 'ITEM_REMOVED', 'item_name' => 'Melted chrome bag'],
        ], $this->notificationsOf($owner)->all());
    }

    public function test_the_owner_can_remove_several_of_their_pieces_without_a_notification(): void
    {
        $owner = User::factory()->create();
        $first = $this->pieceWithPhotos(['user_id' => $owner->id]);
        $second = $this->pieceWithPhotos(['user_id' => $owner->id]);

        $this->asUser($owner)->deleteJson('/api/items', ['item_ids' => [$first->id, $second->id]])->assertStatus(200);

        $this->assertDatabaseCount('items', 0);
        $this->assertCount(0, $this->notificationsOf($owner));
    }

    public function test_nothing_is_removed_when_one_piece_belongs_to_someone_else(): void
    {
        $user = User::factory()->create();
        $own = $this->pieceWithPhotos(['user_id' => $user->id]);
        $foreign = $this->pieceWithPhotos();

        $this->asUser($user)->deleteJson('/api/items', ['item_ids' => [$own->id, $foreign->id]])->assertStatus(403);

        $this->assertDatabaseCount('items', 2);
        $this->assertPhotoFilesExist($own);
    }

    public function test_nothing_is_removed_when_one_piece_is_part_of_an_exchange(): void
    {
        $free = $this->pieceWithPhotos();
        $traded = $this->pieceWithPhotos();
        Exchange::factory()->create(['requested_item_id' => $traded->id]);

        $response = $this->asUser($this->admin())->deleteJson('/api/items', ['item_ids' => [$free->id, $traded->id]]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Cannot delete an item that is part of an exchange.');
        $this->assertDatabaseHas('items', ['id' => $free->id]);
        $this->assertPhotoFilesExist($free);
    }

    public function test_the_piece_list_must_have_existing_pieces_without_repeats(): void
    {
        $piece = $this->pieceWithPhotos();

        foreach ([[], 'all', [$piece->id, $piece->id], [$piece->id, 999]] as $itemIds) {
            $this->asUser($this->admin())->deleteJson('/api/items', ['item_ids' => $itemIds])->assertStatus(422);
        }

        $this->assertDatabaseHas('items', ['id' => $piece->id]);
    }

    public function test_removing_several_pieces_requires_authentication(): void
    {
        $this->deleteJson('/api/items', ['item_ids' => [1]])->assertStatus(401);
    }
}
