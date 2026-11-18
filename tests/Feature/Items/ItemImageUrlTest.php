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

class ItemImageUrlTest extends TestCase
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

    public function test_an_item_image_exposes_its_public_url_and_keeps_the_path(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);
        ItemImage::factory()->create(['item_id' => $item->id, 'path' => 'item-images/photo.jpg']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson("/api/items/{$item->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('images.0.path', 'item-images/photo.jpg');
        $response->assertJsonPath('images.0.url', Storage::disk('public')->url('item-images/photo.jpg'));
    }

    public function test_an_uploaded_item_image_answers_with_its_url(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->postJson("/api/items/{$item->id}/images", [
                'images' => [UploadedFile::fake()->image('photo.jpg')],
            ]);

        $response->assertStatus(201);
        $path = $response->json('0.path');
        $response->assertJsonPath('0.url', Storage::disk('public')->url($path));
    }
}
