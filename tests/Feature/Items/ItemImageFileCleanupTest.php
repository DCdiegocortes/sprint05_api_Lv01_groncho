<?php

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ItemImageFileCleanupTest extends TestCase
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

    public function test_deleting_an_item_image_also_removes_the_file_from_disk(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);
        Storage::disk('public')->put('item-images/photo.jpg', 'content');
        $image = ItemImage::factory()->create(['item_id' => $item->id, 'path' => 'item-images/photo.jpg']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->deleteJson("/api/items/{$item->id}/images/{$image->id}");

        $response->assertStatus(200);
        Storage::disk('public')->assertMissing('item-images/photo.jpg');
    }
}
