<?php

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ItemImagesTest extends TestCase
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

    private function tokenFor(User $user): string
    {
        return $user->createToken('test-token')->accessToken;
    }

    public function test_owner_can_upload_images_to_their_item(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->postJson("/api/items/{$item->id}/images", [
                'images' => [
                    UploadedFile::fake()->image('photo1.jpg'),
                    UploadedFile::fake()->image('photo2.jpg'),
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('item_images', 2);
    }

    public function test_uploading_images_requires_authentication(): void
    {
        $item = Item::factory()->create();

        $response = $this->postJson("/api/items/{$item->id}/images", [
            'images' => [UploadedFile::fake()->image('photo1.jpg')],
        ]);

        $response->assertStatus(401);
    }

    public function test_other_user_cannot_upload_images_to_someone_elses_item(): void
    {
        $intruder = User::factory()->create();
        $item = Item::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->postJson("/api/items/{$item->id}/images", [
                'images' => [UploadedFile::fake()->image('photo1.jpg')],
            ]);

        $response->assertStatus(403);
    }

    public function test_uploading_images_requires_at_least_one_image(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->postJson("/api/items/{$item->id}/images", ['images' => []]);

        $response->assertStatus(422);
    }

    public function test_owner_can_delete_an_image(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);
        $image = ItemImage::factory()->create(['item_id' => $item->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->deleteJson("/api/items/{$item->id}/images/{$image->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('item_images', ['id' => $image->id]);
    }

    public function test_admin_can_delete_any_image(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create(['item_id' => $item->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->deleteJson("/api/items/{$item->id}/images/{$image->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('item_images', ['id' => $image->id]);
    }

    public function test_other_user_cannot_delete_someone_elses_image(): void
    {
        $intruder = User::factory()->create();
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create(['item_id' => $item->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->deleteJson("/api/items/{$item->id}/images/{$image->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('item_images', ['id' => $image->id]);
    }

    public function test_deleting_image_requires_authentication(): void
    {
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create(['item_id' => $item->id]);

        $response = $this->deleteJson("/api/items/{$item->id}/images/{$image->id}");

        $response->assertStatus(401);
    }
}
