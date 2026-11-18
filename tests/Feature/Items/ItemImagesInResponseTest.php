<?php

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ItemImagesInResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test-token')->accessToken;
    }

    public function test_the_item_list_includes_the_images_of_each_item(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);
        ItemImage::factory()->count(2)->create(['item_id' => $item->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson('/api/items');

        $response->assertStatus(200);
        $response->assertJsonCount(2, '0.images');
    }

    public function test_an_item_without_images_returns_an_empty_list(): void
    {
        $owner = User::factory()->create();
        Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson('/api/items');

        $response->assertStatus(200);
        $response->assertJsonCount(0, '0.images');
    }

    public function test_viewing_an_item_includes_its_images(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);
        ItemImage::factory()->count(3)->create(['item_id' => $item->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson("/api/items/{$item->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'images');
    }

    public function test_the_admin_list_includes_the_images_too(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = Item::factory()->create();
        ItemImage::factory()->create(['item_id' => $item->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/items');

        $response->assertStatus(200);
        $response->assertJsonCount(1, '0.images');
    }
}
