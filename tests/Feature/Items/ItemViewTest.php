<?php

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ItemViewTest extends TestCase
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

    public function test_user_sees_only_their_own_items(): void
    {
        $user = User::factory()->create();
        Item::factory()->count(2)->create(['user_id' => $user->id]);
        Item::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->getJson('/api/items');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    public function test_admin_sees_all_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Item::factory()->count(4)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/items');

        $response->assertStatus(200);
        $response->assertJsonCount(4);
    }

    public function test_listing_items_requires_authentication(): void
    {
        $response = $this->getJson('/api/items');

        $response->assertStatus(401);
    }

    public function test_owner_can_view_their_item(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->getJson("/api/items/{$item->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $item->name]);
    }

    public function test_admin_can_view_any_item(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = Item::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson("/api/items/{$item->id}");

        $response->assertStatus(200);
    }

    public function test_other_user_cannot_view_someone_elses_item(): void
    {
        $intruder = User::factory()->create();
        $item = Item::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->getJson("/api/items/{$item->id}");

        $response->assertStatus(403);
    }

    public function test_viewing_item_requires_authentication(): void
    {
        $item = Item::factory()->create();

        $response = $this->getJson("/api/items/{$item->id}");

        $response->assertStatus(401);
    }
}
